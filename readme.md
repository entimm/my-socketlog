# 平时项目调试自己用的socketlog

1. 新加了个load.php，对于一个 PHP 项目，如果是单一入口，或者几乎全部 PHP 文件都调用了一个文件，则可以直接引入这个 `load.php`, 然后直接在需要调试的地方 `slog`

2. 对于多个独立的 PHP 文件来说，则通过向 .htaccess 加入 `auto_prepend_file` 规则即可

3. 默认 socketlog 会把所有错误类型都输出来，当时我只想根据当前所设置的 `error_report` 输出,所以在 `slog.php` 中加了 2 行代码
