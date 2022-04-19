# packagist-mirror
Packagist mirror tool, with distribution included, written in PHP.

This project is inspired by [aliyun/packagist-mirror](https://github.com/aliyun/packagist-mirror), and this project downloads mutiple files at local storage.

## Requirements

- PHP >= 7.4
- Swoole >= 4.5.0
- High-Speed storage, SSD is better

## Usage

```bash
git clone https://github.com/crazywhalecc/packagist-mirror.git
cd packagist-mirror
composer update

bin/mirror sync --log-level=3
```
