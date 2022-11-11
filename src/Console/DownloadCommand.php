<?php

namespace Rocketsoba\Crypto\Console;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Rocketsoba\Crypto\OHLCVDownloader;

class DownloadCommand extends Command
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName("download")
             ->setDescription("Download OHLCV")
             ->addOption(
                 "symbol",
                 null,
                 InputOption::VALUE_REQUIRED,
                 "symbol [required] [example:\"BTCUSDT\"]",
                 ""
             )
             ->addOption(
                 "from",
                 null,
                 InputOption::VALUE_REQUIRED,
                 "from [required] [example:\"2022-01-01\"]",
                 ""
             )
             ->addOption(
                 "to",
                 null,
                 InputOption::VALUE_REQUIRED,
                 "to [required] [example:\"2022-01-31\"]",
                 ""
             )
             ->addOption(
                 "interval",
                 null,
                 InputOption::VALUE_REQUIRED,
                 "interval of candle [optional]",
                 "1m"
             )
             ->addOption(
                 "vratio",
                 null,
                 InputOption::VALUE_REQUIRED,
                 "ratio of volume [optional]",
                 1
             )
             ->addOption(
                 "source",
                 null,
                 InputOption::VALUE_REQUIRED,
                 "source [optional]",
                 "binance"
             )
             ->addOption(
                 "base_csv",
                 null,
                 InputOption::VALUE_REQUIRED,
                 "if this csv contains matched duration, skip download [optional]",
                 ""
             );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption("symbol") === "") {
            throw new Exception("--symbol option is required");
        }
        if ($input->getOption("from") === "") {
            throw new Exception("--from option is required");
        }
        if ($input->getOption("to") === "") {
            throw new Exception("--to option is required");
        }

        $this->logger->pushHandler(new ConsoleHandler($output));

        $symbol   = $input->getOption("symbol");
        $from     = $input->getOption("from");
        $to       = $input->getOption("to");
        $interval = $input->getOption("interval");
        $vratio   = $input->getOption("vratio");
        $source   = $input->getOption("source");
        $base_csv = $input->getOption("base_csv");

        $downloader = new OHLCVDownloader($symbol, $from, $to, $interval, $vratio, $source, $base_csv);
        if ($source === "binance") {
            $data = $downloader->fetchOHLCVFromBinance();
        }
        if ($source === "bybit") {
            $data = $downloader->fetchOHLCVFromBybit();
        }

        return 0;
    }
}
