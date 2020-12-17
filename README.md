# Composer Installers

## 使用方法

``` sh
composer require ank/installer
```

## 配置方法

新创建一种纯静态资源包类型 `static`,包配置如下：

```json
{
    "name": "ank/admin",
    "type": "static",
    "license": "MIT",
}
```

安装的时候需要安装到当前项目的 `web/public` 目录下面,

在根`composer.json` 中添加上面自定义类型的支持

``` json
 "extra": {
      "installer-types": ["static"],
      "installer-paths": {
      "special/package/{$name}": ["ank/admin"],
      "web/public/{$name}/": ["type:static"],
      "web/vendor/{$name}/":["vendor:my_organization"]
    }
 }
```

可使用三种方法来匹配安装包,
1、直接使用包名字
2、按类型来匹配
3、按供应商名字来归类

路径中可以使用的变量 `{$name}` `{$vendor}` `{$type}`

默认情况下如果一个包不指定type则默认为 `library`,安装的时候如果这个类型已经添加到 `"installer-types"`, 插件将会云查询映射的安装路径.如果没有匹配到则使用默认安装路径。

映射路径规则，可以参考
 [`composer/installers`](https://github.com/composer/installers#custom-install-paths):

其它请查看  [`composer/installers`](https://github.com/composer/installers) 的README 文档 (e.g. `{$name}`).
