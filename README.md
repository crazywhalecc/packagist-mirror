# packagist-mirror
Packagist mirror tool, with distribution included, written in PHP.

This project is inspired by [aliyun/packagist-mirror](https://github.com/aliyun/packagist-mirror), and this project downloads mutiple files at local storage.

## Features

- Single process, single thread, multi-coroutine runtime
- It contains not only metadata, but also distribution files

## Requirements

- PHP >= 7.4
- Swoole >= 4.5.0
- High-Speed storage, SSD is better

## Configuration

Configuration file is `config.php`, remember fill the blanks, and github token is required.

`repo.packagist.org` is the default mirror source, you can change it in config file.

## Usage

```bash
git clone https://github.com/crazywhalecc/packagist-mirror.git
cd packagist-mirror
composer update

bin/mirror sync --log-level=3
```

## Bugs

- Not stable yet
