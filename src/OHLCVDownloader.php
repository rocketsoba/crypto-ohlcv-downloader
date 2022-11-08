<?php

namespace Rocketsoba\Crypto;

use Exception;
use Rocketsoba\Curl\MyCurl;
use Rocketsoba\Curl\MyCurlBuilder;
use Rocketsoba\DomParserWrapper\DomParserAdapter;
use ZipArchive;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\Writer;

class OHLCVDownloader
{
    private $base_url = 'https://www.bitmex.com/api/udf/history?';
    private $binance_base_url = 'https://data.binance.vision/data/futures/um/daily/klines/';
    private $bybit_base_url = 'https://public.bybit.com/trading/';
    private $max_vars = 10080;
    private $ratelimit_limit = 30;
    private $ratelimit_remaining;
    private $ratelimit_reset;
    private $symbol;
    private $from;
    private $to;
    private $interval;
    private $source;
    private $vratio;
    public $data;

    public function __construct($symbol = "", $from = "", $to = "", $interval = "1m", $vratio = 1, $source = "bitmex")
    {
        $this->symbol   = $symbol;
        $this->from     = $from;
        $this->to       = $to;
        $this->interval = $interval;
        $this->vratio   = $vratio;
        $this->source   = $source;
    }

    public function fetchOHLCVFromBybit($symbol = "", $from = "", $to = "")
    {
        if ($symbol !== "") {
            $this->symbol = $symbol;
        }
        if ($from !== "") {
            $this->from = $from;
        }
        if ($to !== "") {
            $this->to = $to;
        }

        /**
         * Throwable
         */

        $output_filename = $this->symbol . $this->interval . ".csv";
        $timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $ymd_from = date("Y-m-d", strtotime($this->from));
        $unixtime_from = strtotime($this->from);
        $unixtime_to = strtotime($this->to);
        $range = (int)(($unixtime_to - $unixtime_from) / 86400);
        $bybit_base_url2 = $this->bybit_base_url . $this->symbol . "/";

        $writer = Writer::createFromPath(getcwd() . "/" . $output_filename, 'w+');
        foreach (range(0, $range) as $idx1 => $val1) {
            $filename = $this->symbol . date("Y-m-d", strtotime("+" . $val1 . "days", $unixtime_from));
            $constructed_url = $bybit_base_url2 . $filename . ".csv.gz";
            /**
             * ファイルが64MB以上あるとメモリ不足になる
             */
            $curl_object = $this->request("GET", $constructed_url, ["file_dest" => getcwd() . "/" . $filename . ".csv.gz"]);
            if ($curl_object->getHttpCode() !== 200) {
                throw new Exception("Fetch sequence is failed");
            }

            /**
             * file_put_contents(getcwd() . "/" . $filename . ".csv.gz", $curl_object->getResult());
             */
            $curl_object = null;
            /**
             * file_exists()すべき
             */
            $zp = gzopen(getcwd() . "/" . $filename . ".csv.gz", "rb");
            $fp = fopen(getcwd() . "/" . $filename . ".csv", "wb");
            while (1) {
                $data = gzread($zp, 1048576);
                if ($data === false || strlen($data) === 0) {
                    break;
                }
                fwrite($fp, $data);
            }
            fclose($fp);
            gzclose($zp);

            $cmd = ['sort', '-n', getcwd() . "/" . $filename . '.csv', '-o' , getcwd() . "/" . $filename . '_2.csv'];
            $process = proc_open($cmd, [], $pipes);
            if ($process === false || proc_close($process) === 1) {
                throw new Exception("Error occured while sorting");
            }

            $interval_from = strtotime("+" . $val1 . "days", $unixtime_from);
            $interval_to = strtotime("+1min", $interval_from);
            $reader = Reader::createFromPath(getcwd() . "/" . $filename . "_2.csv", "r");
            $reader->setHeaderOffset(0);
            $first_record = $reader->fetchOne(0);
            if ($first_record["timestamp"] < $interval_from || $first_record["timestamp"] >= $interval_to) {
                throw new Exception("First record is out of range of interval");
            }

            $data = [];
            $current_data = [
                "date" => date("Y.m.d", (int)$interval_from),
                "minute" => date("H:i", (int)$interval_from),
                "open" => $first_record["price"],
                "high" => $first_record["price"],
                "low" => $first_record["price"],
                "close" => $first_record["price"],
                "volume" => 0,
            ];
            if (!empty($data)) {
                $data[count($data) - 1]["close"] = $first_record["price"];
            }

            foreach ($reader as $idx2 => $val2) {
                if ($val2["timestamp"] >= $interval_to && $val2["timestamp"] < strtotime("+1min", $interval_to)) {
                    $interval_from = $interval_to;
                    $interval_to = strtotime("+1min", $interval_from);

                    $current_data["close"] = $val2["price"];
                    $data[] = $current_data;

                    $current_data = [
                        "date" => date("Y.m.d", (int)$interval_from),
                        "minute" => date("H:i", (int)$interval_from),
                        "open" => $val2["price"],
                        "high" => $val2["price"],
                        "low" => $val2["price"],
                        "close" => $val2["price"],
                        "volume" => 1,
                    ];
                    continue;
                }
                if ($val2["timestamp"] >= strtotime("+1min", $interval_to)) {
                    /**
                     * その期間内のデータがない場合
                     */
                    $interval_from = $interval_to;
                    $interval_to = strtotime("+1min", $interval_from);

                    $prev_price = $current_data["close"];
                    $data[] = $current_data;
                    /**
                     * ここまで前の期間分
                     */

                    while (1) {
                        if ($val2["timestamp"] >= $interval_from && $val2["timestamp"] < $interval_to) {
                            break;
                        }

                        $data[] = [
                            "date" => date("Y.m.d", (int)$interval_from),
                            "minute" => date("H:i", (int)$interval_from),
                            "open" => $prev_price,
                            "high" => $prev_price,
                            "low" => $prev_price,
                            "close" => $prev_price,
                            "volume" => 1,
                        ];
                        $interval_from = $interval_to;
                        $interval_to = strtotime("+1min", $interval_from);
                    }

                    $current_data = [
                        "date" => date("Y.m.d", (int)$interval_from),
                        "minute" => date("H:i", (int)$interval_from),
                        "open" => $val2["price"],
                        "high" => $val2["price"],
                        "low" => $val2["price"],
                        "close" => $val2["price"],
                        "volume" => 1,
                    ];
                }


                if ($current_data["high"] < $val2["price"]) {
                    $current_data["high"] = $val2["price"];
                }
                if ($current_data["low"] > $val2["price"]) {
                    $current_data["low"] = $val2["price"];
                }
                $current_data["close"] = $val2["price"];
                $current_data["volume"]++;
                $prev = $val2;
            }
            if ($data[count($data) - 1]["minute"] !== $current_data["minute"]) {
                $data[] = $current_data;
            }
            if (count($data) !== 1440) {
                /**
                 * その期間内のデータがない場合
                 */
                $interval_from = strtotime("+1min", $interval_from);

                /**
                 * ここまで前の期間分
                 */

                while (1) {
                    if (count($data) === 1440) {
                        break;
                    }

                    $data[] = [
                        "date" => date("Y.m.d", (int)$interval_from),
                        "minute" => date("H:i", (int)$interval_from),
                        "open" => $data[count($data) - 1]["close"],
                        "high" => $data[count($data) - 1]["close"],
                        "low" => $data[count($data) - 1]["close"],
                        "close" => $data[count($data) - 1]["close"],
                        "volume" => 1,
                    ];
                    $interval_from = strtotime("+1min", $interval_from);
                }
            }
            $writer->insertAll($data);

            unlink(getcwd() . "/" . $filename . ".csv");
            unlink(getcwd() . "/" . $filename . "_2.csv");
            unlink(getcwd() . "/" . $filename . ".csv.gz");
        }

        date_default_timezone_set($timezone);

        return $this;
    }

    public function fetchOHLCVFromBinance($symbol = "", $from = "", $to = "", $interval = "1m")
    {
        if ($symbol !== "") {
            $this->symbol = $symbol;
        }
        if ($from !== "") {
            $this->from = $from;
        }
        if ($to !== "") {
            $this->to = $to;
        }
        if ($interval !== "1m") {
            $this->interval = $interval;
        }

        /**
         * Throwable
         */
        $data = [];

        $output_filename = $this->symbol . $this->interval . ".csv";
        $timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $ymd_from = date("Y-m-d", strtotime($this->from));
        $unixtime_from = strtotime($this->from);
        $unixtime_to = strtotime($this->to);
        $range = (int)(($unixtime_to - $unixtime_from) / 86400);
        $binance_base_url2 = $this->binance_base_url . $this->symbol . "/" . $this->interval . "/";

        $writer = Writer::createFromPath($output_filename, 'w+');
        foreach (range(0, $range) as $idx1 => $val1) {
            $filename = $this->symbol . "-" . $this->interval . "-" . date("Y-m-d", strtotime("+" . $val1 . "days", $unixtime_from));
            $constructed_url = $binance_base_url2 . $filename . ".zip";
            $curl_object = $this->request("GET", $constructed_url);
            if ($curl_object->getHttpCode() !== 200) {
                throw new Exception("Fetch sequence is failed");
            }
            file_put_contents(getcwd() . "/" . $filename . ".zip", $curl_object->getResult());

            $zip = new ZipArchive();
            if ($zip->open($filename . ".zip") === true) {
                $zip->extractTo(getcwd());
                $zip->close();
            } else {
                throw new Exception("Error occurred while extrarcting zip file");
            }

            $reader = Reader::createFromPath($filename . ".csv", "r");
            $vratio = $this->vratio;
            $ohlcv_reords = array_map(function ($value) use ($vratio) {
                if (!is_numeric($value[0])) {
                    return [];
                }
                return [
                    date("Y.m.d", (int)($value[0] / 1000)),
                    date("H:i", (int)($value[0] / 1000)),
                    $value[1],
                    $value[2],
                    $value[3],
                    $value[4],
                    (int)($value[5] * $vratio),
                ];
            }, iterator_to_array($reader->getRecords()));
            $ohlcv_reords = array_filter($ohlcv_reords);
            $writer->insertAll($ohlcv_reords);

            unlink($filename . ".csv");
            unlink($filename . ".zip");
        }

        date_default_timezone_set($timezone);

        return $this;
    }

    public function fetchOHLCVFromBitmex($symbol = "", $from = "", $to = "", $interval = "1m")
    {
        if ($symbol !== "") {
            $this->symbol = $symbol;
        }
        if ($from !== "") {
            $this->from = $from;
        }
        if ($to !== "") {
            $this->to = $to;
        }
        if ($interval !== "1m") {
            $this->interval = $interval;
        }

        /**
         * Throwable
         */
        $data = [];

        $timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $unixtime_from = strtotime($this->from);
        $unixtime_to   = strtotime($this->to);

        $query = [
            "symbol" => $this->symbol,
            "from" => $unixtime_from,
            "to" => $unixtime_to,
            "resolution" => $this->interval,
        ];
        $constructed_url = $this->base_url . http_build_query($query);
        var_dump($constructed_url);
        die();
        $curl_object = $this->request("GET", $constructed_url);
        /**
         * s:okを確認する
         */
        if ($curl_object->getHttpCode() !== 200) {
            throw new Exception("Fetch sequence is failed");
        }
        /**
         * ratelimitの処理を書く
         */

        $result = json_decode($curl_object->getResult(), true);
        if (
            !isset($result["t"]) ||
            !isset($result["o"]) ||
            !isset($result["h"]) ||
            !isset($result["c"]) ||
            !isset($result["l"]) ||
            !isset($result["v"])
        ) {
            throw new Exception("API result is something wrong");
        }
        $ohlcv_time = array_map(function ($value) {
            return date('Y.m.d,H:i', $value);
        }, $result["t"]);

        /**
         * 転置行列を作る
         * https://stackoverflow.com/questions/797251/transposing-multidimensional-arrays-in-php
         */
        $ohclv_data =
            array_map(
                null,
                $ohlcv_time,
                $result["o"],
                $result["h"],
                $result["l"],
                $result["c"],
                $result["v"]
            );

        $this->data = $ohclv_data;
        date_default_timezone_set($timezone);

        return $this;
    }

    public function request($method, $uri, $options = [])
    {
        $curl_object = new MyCurlBuilder($uri);

        if (isset($options["headers"])) {
            $curl_object = $curl_object->setAddtionalHeaders($options["headers"]);
        }
        if (isset($options["rest_post_data"])) {
            $curl_object = $curl_object->setPlainPostData($options["rest_post_data"]);
        }
        if (isset($options["array_post_data"])) {
            $curl_object = $curl_object->setPostData($options["array_post_data"]);
        }
        if (isset($options["file_dest"])) {
            $curl_object = $curl_object->setFilePointerMode($options["file_dest"]);
        }

        $curl_object = $curl_object->build();
        return $curl_object;
    }

    public function getData()
    {
        return $this->data;
    }
}
