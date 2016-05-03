# 平时项目调试自己用的socketlog

## 新加了个load.php

1. 对于一个 PHP 项目，如果是单一入口，或者几乎全部 PHP 文件都调用了一个文件，则可以直接引入这个 `load.php`, 然后直接在需要调试的地方 `slog`

2. 对于多个独立的 PHP 文件来说，则通过向 .htaccess 加入 `auto_prepend_file` 规则即可
