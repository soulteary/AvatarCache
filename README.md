## AvatarCache

鉴于众所周知的原因，访问Gravatar也越来越慢了，如果一个页面中有大量评论都是引用Gravatar的图片作为头像的话，那么页面基本要转半天菊花。

与其如此，不如自己搭一个简单靠谱的Gravatar缓存。

或许在许多年以前你已经见过了其他人写的同类型脚本，但是似乎“前人”写的脚本似乎存在以下问题：

- 对Gravatar的一些细节没有“照顾”好。
- 对浏览器缓存支持不太好。
- 对URL美化支持不足。
- 不支持多尺寸的图片下载。
- 必须连接数据库，不能独立存在。

鉴于此，花了一天多更新了这个脚本，希望能帮到你。

## 计划列表

- 添加微博头像支持
- 添加微信头像支持
- 添加QQ头像支持
- 添加“刷新缓存”接口
- 添加“统计状态”接口
- 添加“删除缓存”接口
- 添加“防盗链”密钥接口（单纯的Refer是解决不了防盗链的）
- 添加“性能对比”文档

## Demo

[Demo Page](http://assets.soulteary.com/avatar/demo/demo.html)

## Nginx

如果你使用的是nginx，不妨根据自己的情况，添加如下配置，使脚本正常运行。

```
# Avatar
location ~ /avatar {
   if (!-f $request_filename) {
        rewrite ".*(\w{32})((\/)?(\d+)?)?$" /avatar/index.php?r=$1&s=$4 break;
        proxy_pass http://$host;
    }
}
```

如果你希望一定程度上防止站外滥用。

```
valid_referers none blocked server_names *.your_domain.com your_domain.com;
if ($invalid_referer) {
    rewrite ^/ "http://www.baidu.com/s?wd=妈妈说不要盗链" last;
    return 404;
}

```

## 额外说明

- Demo 中默认是以我的assets路径为地址的，因为有Refer限制，请下载源码后修改为你的地址访问，或者访问在线Demo。
- placeholder 如果不需要那么多尺寸（你自己限制几种的话，那么无须上传那么多尺寸的图片，虽然也不大）。
- 如果你使用第三方评论系统，可以使用js来替换页面中的评论头像地址。
    - 不完美的解决方案: [example](demo/example.js)
- 如果使用PHP 5.5+，可能默认是没有curl库的:
    - 请执行以下命令安装：
    - `sudo apt-get install php5-curl`
    - `sudo service apache2 restart`