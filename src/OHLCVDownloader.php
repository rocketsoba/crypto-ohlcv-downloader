<?php

namespace Rocketsoba\Crypto;

use Exception;
use Rocketsoba\Curl\MyCurl;
use Rocketsoba\Curl\MyCurlBuilder;
use Rocketsoba\DomParserWrapper\DomParserAdapter;
use ZipArchive;
use League\Csv\Reader;
use League\Csv\Writer;

class OHLCVDownloader
{
    private $base_url = 'https://www.bitmex.com/api/udf/history?';
    private $binance_base_url = 'https://data.binance.vision/data/futures/um/daily/klines/';
    private $max_vars = 10080;
    private $ratelimit_limit = 30;
    private $ratelimit_remaining;
    private $ratelimit_reset;
    private $symbol;
    private $from;
    private $to;
    private $interval;
    private $source;
    public $data;

    public function __construct($symbol = "", $from = "", $to = "", $interval = "1m", $source = "bitmex")
    {
        $this->symbol   = $symbol;
        $this->from     = $from;
        $this->to       = $to;
        $this->interval = $interval;
        $this->source   = $source;
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
            $ohlcv_reords = array_map(function ($value) {
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
                    (int)$value[5],
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

        $curl_object = $curl_object->build();
        return $curl_object;
    }

    public function getData()
    {
        return $this->data;
    }
}
