# crypto-ohlcv-downloader

## Installation

### Local
```
$ git clone https://github.com/rocketsoba/crypto-ohlcv-downloader
$ composer install
```

### Global
```
# This library is not available on Packagist, so you need to add repository manually.
$ composer global config repositories.crypto-ohlcv-downloader '{"type": "vcs", "url": "https://github.com/rocketsoba/crypto-ohlcv-downloader", "no-api": true}'
$ composer global require rocketsoba/crypto-ohlcv-downloader


# If you haven't add composer bin-dir to the $PATH, please configure your $PATH.
# Default composer bin-dir is "$HOME/.composer/vendor/bin"
$ export PATH=$PATH:$HOME/.composer/vendor/bin
```

## Command
```
$ ./crypto-ohlcv-downloader --help
Description:
  Download OHLCV

Usage:
  download [options]

Options:
      --symbol=SYMBOL      symbol [required] [example:"BTCUSDT"] [default: ""]
      --from=FROM          from [required] [example:"2022-01-01"] [default: ""]
      --to=TO              to [required] [example:"2022-01-31"] [default: ""]
      --interval=INTERVAL  interval of candle [optional] [default: "1m"]
      --vratio=VRATIO      ratio of volume [optional] [default: 1]
      --source=SOURCE      source [optional] [default: "binance"]
      --base_csv=BASE_CSV  if this csv contains matched duration, skip download [optional] [default: ""]
  -h, --help               Display help for the given command. When no command is given display help for the download command
  -q, --quiet              Do not output any message
  -V, --version            Display this application version
      --ansi|--no-ansi     Force (or disable --no-ansi) ANSI output
  -n, --no-interaction     Do not ask any interactive question
  -v|vv|vvv, --verbose     Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```
