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
                 "symbol",
                 ""
             )
             ->addOption(
                 "from",
                 null,
                 InputOption::VALUE_REQUIRED,
                 "from",
                 ""
             )
             ->addOption(
                 "to",
                 null,
                 InputOption::VALUE_REQUIRED,
                 "to",
                 ""
             )
             ->addOption(
                 "interval",
                 null,
                 InputOption::VALUE_OPTIONAL,
                 "interval of candle",
                 "1m"
             )
             ->addOption(
                 "vratio",
                 null,
                 InputOption::VALUE_OPTIONAL,
                 "ratio of volume",
                 1
             )
             ->addOption(
                 "source",
                 null,
                 InputOption::VALUE_OPTIONAL,
                 "source",
                 "bitmex"
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

        $downloader = new OHLCVDownloader($symbol, $from, $to, $interval, $vratio, $source);
        if ($source === "bitmex") {
            $data = $downloader->fetchOHLCVFromBinance();
        }
        if ($source === "bybit") {
            $data = $downloader->fetchOHLCVFromBybit();
        }

        /**
         * $output->writeln($data);
         */

        return 0;
    }
}
