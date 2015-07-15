<?php
/**
 * 静态头像缓存
 *
 * @author soulteary
 * @website soulteary.com
 *
 * @desc 你自己的头像缓存服务
 *
 * @example
 *
 *      correct mail && exist user:
 *          process('70a2291293cd289ae0573b6cdb7d791e');
 *          process(md5(strtolower('soulteary@qq.com')));
 *
 *      counterexample:
 *          process('25b32aea0632d90b8b232d382d08d228');
 *
 */

/** 管理密码 **/
define( 'Token', 'PLEASE_UPDATE_YOUR_TOKEN' );


define( 'version', '2015.01.15' );

/** 静态域名定义 **/
define( 'AssetsDomain', 'http://assets.soulteary.com/' );
/** 静态资源目录 **/
define( 'AssetsDir', 'avatar/' );
/** 资源服务器API **/
define( 'GravatarApi', 'http://0.gravatar.com/avatar/' );

/** 脚本执行目录 **/
define( 'ScriptDir', dirname( $_SERVER['SCRIPT_FILENAME'] ) . '/' );

/** 静态资源路径 **/
define( 'AssetsPath', AssetsDomain . AssetsDir );

/** 占位图路径 **/
define( 'PlaceHolderAvatar', 'placeholder/SIZE.png' );
/** 默认图片尺寸 **/
define( 'DefaultSize', 42 );
/** 最大文件尺寸, 如果不需要限制，可以考虑设置为 -1，但是请注意，占位图最大406 */
define( 'MaxSize', 80 );

/** 将头像文件分目录存放 **/
define('SplitImage', true);

/** 过期时间为30天 **/
define( 'ExpireDate', 259200 );

/** 图片限制级别 **/
define( 'AvatarGrade', 'G' );

/** 选择缓存模式，如果false，则使用代理模式 **/
define( 'CacheMode', true );

/** 选择调试模式 **/
define( 'DebugMode', false );

/** 选择读取文件模式，1=>PHP READ FILE CONTENT，2=>PHP HEADER REDIRECT **/
/** 注意: 选择模式2后，如果要限制其他域名访问资源，需要在服务器规则中更新配置 **/
define( 'FileMethod', 1 );

/** 仅允许某些网站进行资源访问 **/
define( 'PrivateMode', true );
/** 允许访问资源的网站主域名，多个域名请用|分隔 **/
define( 'PrivateDomain', 'soulteary.com' );


/**
 * 图片缓存函数
 *
 * @param $hash
 * @param int $size
 * @param string $default
 * @param $debugMode
 */
function process( $hash, $size = DefaultSize, $default = '', $debugMode = false ) {
    /** 错误码 **/
    $errorCode = array(
        200 => '成功建立缓存。',
        302 => '文件已被缓存，使用默认占位图片。',
        400 => '网络或磁盘写入问题导致建立缓存失败。',
        404 => '资源接口没有这个数据，使用默认占位图。',
        500 => '网络异常或资源服务器接口出错，使用默认占位图。'
    );

    // 缓存目录
    $subDir = 'cache/';
    // 默认图片额外后缀
    $defExt = '.def';
    // 当前的时间
    $now = time();

    $file      = $hash;
    $scriptDir = ScriptDir;

    // 最大尺寸范围为1~406（默认占位图最大尺寸）
    if ( ! $size || $size > MaxSize || $size > 406 ) {
        $size = DefaultSize;
    }

    // 调试模式开关
    $debugMode = $debugMode || DebugMode;
    // 调试信息保存
    $debugInfo = Array();

    // 如果使用分目录存放图片的功能
    if(SplitImage){
        // 形成诸如 /avatar/cache/70a2/2912/93cd/289a/e057/3b6c/db7d/791e/$file.$size的文件缓存
        $filePath = implode(str_split($hash, 4), '/') . '/';
    }else{
        // 形成诸如 /avatar/cache/md5.Size的文件缓存
        $filePath = $scriptDir . $subDir . $file . '.' . $size;
    }

    // 临时参数
    $params = array(
        'type'   => null,
        'path'   => $filePath,
        'code'   => 302,
        'uri'    => AssetsPath . $subDir . $file . '.' . $size,
        'useDef' => false
    );

    // 未设定默认图片的时候，使用默认占位图
    if ( empty( $default ) ) {
        $default = str_replace( 'SIZE', $size, PlaceHolderAvatar );
    }

    $debugMode && array_push( $debugInfo, "1.打底参数:" . json_encode( $params ) );

    // 是否尝试缓存文件
    $tryCache = false;
    // 如果文件（包括默认占位图）不存在
    if ( ! file_exists( $params['path'] ) ) {
        // 尝试创建目录
        if(SplitImage){
            @mkdir(dirname($params['path']), 755, true);
        }
        // 查看文件默认占位图是否存在（只可能为PNG）
        if ( ! file_exists( $params['path'] . $defExt . '.is.png' ) ) {
            $debugMode && array_push( $debugInfo, "2.文件不存在，尝试缓存文件:" . $params['path'] );
            $tryCache = true;
        } else {
            // 查看文件默认占位图是否超出设定缓存时间
            if ( file_exists( $params['path'] . $defExt . '.is.png' ) ) {
                if ( ( $now - filemtime( $params['path'] . $defExt . '.is.png' ) ) > ExpireDate ) {
                    $tryCache = true;
                    $debugMode && array_push( $debugInfo, "2.文件默认占位图缓存超过保质期，尝试重新缓存文件:" . $params['path'] );
                }
            } else {
                // 默认占位图只存在1种可能
                showError();
            }
        }
    } else {
        // 储存的文件超出设定缓存时间
        if ( ( $now - filemtime( $params['path'] ) ) > ExpireDate ) {
            $tryCache = true;
            $debugMode && array_push( $debugInfo, "2.储存的文件超出设定缓存时间，尝试重新缓存文件:" . $params['path'] );
        }
    }

    // 判断是否要进行重新缓存操作
    if ( $tryCache ) {
        $grade  = AvatarGrade;
        $origin = GravatarApi . $file . '?s=' . $size . '&d=' . urlencode( $default ) . '&r=' . $grade;

        // 由于资源服务器未标识资源类型，所以第一次先取一次文件信息
        // 这里使用包装带超时功能的函数取代 get_headers($origin, 1)
        $fileHeader = get_url_headers( $origin );

        $debugMode && array_push( $debugInfo, "3.获取远程服务器文件[" . $origin . "]基础信息:" . json_encode( $fileHeader ) );

        // 判断接口有效性
        if ( isset( $fileHeader['Content-Length'] ) ) {
            $fileSize = $fileHeader['Content-Length'];
        } else {
            $fileSize = 0;
        }
        if ( isset( $fileHeader['Content-Type'] ) ) {
            $fileType = $fileHeader['Content-Type'];
        } else {
            $fileType = '';
        }

        $debugMode && array_push( $debugInfo, "4.修正后的文件基础信息:" . $fileSize . 'bytes;' . $fileType );

        // 当且仅当文件尺寸和类型都存在，且类型为限定类型时
        if ( $fileSize && $fileType && in_array( $fileType, array( 'image/png', 'image/jpeg', 'image/jpg' ) ) ) {
            // 尝试进行缓存
            $isCached = copy( $origin, $params['path'] );
            // 缓存成功
            if ( $isCached ) {
                // 如果他家的资源接口有问题数据的话
                if ( strtolower( $fileType ) == 'image/jpg' ) {
                    $fileType = 'image/jpeg';
                }
                // 通过文件标记图片类型
                copy( $scriptDir . 'this.is.a.placeholder', $params['path'] . '.is.' . str_replace( 'image/', '', $fileType ) );
                $params['type'] = $fileType;
                $params['code'] = 200;
                $debugMode && array_push( $debugInfo, "5.缓存文件成功:" . $params['path'] );
            } else {
                // 如果没有建立类型标记，那么不需要缓存这个文件
                if ( is_file( $params['path'] ) ) {
                    unlink( $params['path'] );
                }
                $params['code'] = 400;
                // 保存使用默认图片标记
                $params['type'] = 'image/png';
                copy( $scriptDir . 'this.is.a.placeholder', $params['path'] . $defExt . '.is.png' );
                $params['useDef'] = true;
                $debugMode && array_push( $debugInfo, "5.缓存文件失败，创建默认图片:" . $params['path'] . $defExt . '.is.png' );
            }
        } else {
            // 如果邮箱未注册或者是临时的假占位邮箱，判断是否存在转向操作
            $isRedirect = $fileHeader['Location'];
            if ( $isRedirect ) {
                $params['code'] = 404;
            } else {
                $params['code'] = 500;
            }
            // 保存使用默认图片标记
            $params['type'] = 'image/png';
            copy( $scriptDir . 'this.is.a.placeholder', $params['path'] . $defExt . '.is.png' );
            $params['useDef'] = true;
            $debugMode && array_push( $debugInfo, "5.文件信息不符合缓存策略，尝试将文件转向默认图片:" . $params['path'] . $defExt . '.is.png' );
        }
    }

    // 判断是否新创建文件
    $isNewFile = false;

    // 获取当前文件的媒体类型和路径
    // 当前没有直接进行下载操作时，code范围为[any, 199] && [300, any]
    if ( $params['code'] > 299 || $params['code'] < 200 ) {
        // 存在有效文件的时候，code范围为[300, 399]
        if ( $params['code'] > 299 && $params['code'] < 400 ) {
            // 判断文件类型
            if ( is_file( $params['path'] . '.is.png' ) ) {
                $params['type'] = 'image/png';
            } elseif ( is_file( $params['path'] . '.is.jpeg' ) ) {
                $params['type'] = 'image/jpeg';
            } else {
                // 尝试查找已经缓存过的默认图片
                if ( is_file( $params['path'] . $defExt . '.is.png' ) ) {
                    $debugMode && array_push( $debugInfo, "2.创建默认占位图：" . $params['path'] . $defExt . '.is.png' );
                    $params['type'] = 'image/png';
                } else {
                    // 如果出现这种情况，可能是之前服务器恰好重启或者文件handle用完，
                    // 或者之前磁盘写满，没有创建成功标记文件，又或者未更新文件类型等特殊异常

                    // 尝试重新建立标记文件
                    unlink( $params['path'] . $defExt . '.is.png' );
                    $params['type'] = 'image/png';
                    copy( $scriptDir . 'this.is.a.placeholder', $params['path'] . $defExt . '.is.png' );
                    $debugMode && array_push( $debugInfo, "6.创建默认占位图失败，尝试重新创建:" . $params['path'] . $defExt . '.is.png' );
                }
                $params['useDef'] = true;
            }
        } else {
            // 不存在文件的时候，code范围为[400, any] && [any, 199]
            // 保存使用默认图片标记
            $params['type'] = 'image/png';
            copy( $scriptDir . 'this.is.a.placeholder', $params['path'] . $defExt . '.is.png' );
            $params['useDef'] = true;
        }
    } else {
        // 这是一个刚刚创建的文件
        $isNewFile = true;
    }

    // 开始输出文件
    date_default_timezone_set( 'PRC' );
    header( 'Age: ' . ExpireDate );
    header( 'Content-Type: ' . $params['type'] );

    // 如果之前标记了使用默认，那么替换路径
    if ( $params['useDef'] ) {
        $params['path'] = $scriptDir . $default;
        $params['uri']  = AssetsPath . $default;
        $debugMode && array_push( $debugInfo, "3.重置打底参数：" . json_encode( $params ) );
    } else {
        $debugMode && array_push( $debugInfo, "3.最终的打底参数：" . json_encode( $params ) );
    }

    // 调试模式
    if ( $debugMode ) {
        header( 'Debug-Message: ' . json_encode( $errorCode[ $params['code'] ] ) );
        header( 'Debug-Message-Code: ' . json_encode( $params['code'] ) );
        header( 'Debug-Message-FileMethod: ' . FileMethod );
        // 调试信息隐藏真实路径
        header( 'Debug-Message-FilePath: ' . '{$scriptDir}/' . str_replace( $scriptDir, '', $params['path'] ) );
        if ( $size == DefaultSize ) {
            header( 'Debug-Message-FileUri: ' . $params['uri'] );
            header( 'Debug-Message-FileFakeUri: ' . AssetsPath . $hash );
        } else {
            header( 'Debug-Message-FileUri: ' . $params['uri'] );
            header( 'Debug-Message-FileFakeUri: ' . $params['uri'] . '/' . $size );
        }
        if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
            header( 'Debug-Message-Refer: ' . $_SERVER['HTTP_REFERER'] );
        }
        header( 'Debug-Message-Info: ' . json_encode( str_replace( $scriptDir, '', implode( "\n", $debugInfo ) ) ) );
    }

    // 调试模式或者不进行缓存时
    if ( $debugMode || ! CacheMode ) {
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Content-Length: ' . filesize( $params['path'] ) );
        header( "Pragma: no-cache" );

        $justNow = gmdate( 'D, d M Y H:i:s', time() ) . ' GMT';

        header( 'Date: ' . $justNow );
        header( 'Expires: ' . $justNow );
        header( 'Last-Modified: ' . $justNow );

    } else {
        header( 'Cache-Control: max-age=' . ExpireDate );

        $fileTime         = filectime( $params['path'] );
        $filePubTime      = strtotime( '+1 days', $fileTime );
        $fileExpTime      = strtotime( '+30 days', $fileTime );
        $fileLastModified = gmdate( 'D, d M Y H:i:s', filemtime( $params['path'] ) );

        header( 'Date: ' . gmdate( 'D, d M Y H:i:s', $filePubTime ) . ' GMT' );
        header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', $fileExpTime ) . ' GMT' );
        header( 'Last-Modified: ' . $fileLastModified . ' GMT' );

        if ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {
            header( 's:' . strpos( $_SERVER['HTTP_IF_MODIFIED_SINCE'], $fileLastModified ) );
            if ( false !== strpos( $_SERVER['HTTP_IF_MODIFIED_SINCE'], $fileLastModified ) ) {
                header( $_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified' );
            } else {
                header( 'Content-Length: ' . filesize( $params['path'] ) );
            }
        } else {
            header( 'Content-Length: ' . filesize( $params['path'] ) );
        }

    }
    // 程序版本信息输出
    header( 'AvatarCache: powered by soulteary.com v' . version );

    // 选择文件读写模式
    switch ( FileMethod ) {
        case 1:
            $debugMode && header( 'Debug-Result: File Get Contents ' . $params['path'] );
            exit( file_get_contents( $params['path'] ) );
            break;
        case 2:
        default:
            $debugMode && header( 'Debug-Result: Redirect To File ' . $params['uri'] );
            header( 'Location: ' . $params['uri'] );
            exit( 0 );
            break;
    }
}


/**
 * 展示错误请求图片
 *
 * @notice 这里推荐只处理合法请求中hash位数不正确的请求
 */
function showError() {
    $imgData = base64_decode( 'R0lGODlhFgAWAPf/AP/61fvjffrVQ/3jbf3fXf3TNv3MHJhFHJ9lGv3jdf7jcfDYj/7mfKVhIL+NBXM5D6VLI6FKIXA1D5pIHXAvEIw4Gf3cVJpDHue5Cf3jaf7vov7unP3WQLR/BP7wpP/50P3gYefc0P7ogv/0uf3jav3ZS/LNO+m9E/v7+//zs8uZC9+yFP7ofuzLTP3aTdinBv/3wP///7F8CPHSY/XSSf3VOO3DHv3cU96tCdaoMP/xrf7leMKPBf3dWPXVUv/1vf/1uv/4yq1wLOvBIqNeHNG7pNuxVKFZJ/v593FZQVq16r6JCP3oh+CwQPr6+ui8EdSvQNyvRc6gTPjTRv/3xq1vJe3JPP3WPrd8NWuVmJ5WJ7a9ivzuqofN+ahoH+3HPsKkhoLt/J2eXc6dH4BKKPf392aawfvZT/nPOJPV/ujSbOOyFne16Hy77PTJO+O4Lf7nfezi2Ii52v3wsf3phP7ohv3UO/rhgPfaWv/4z8meKnmbkfzeY5xOGtjFsv753v3NHqJbIf3gZ7eDDpZGGuC3T+W7StCiTMO4WNapSv74wV6jxVev3/jTQ9KeQYthKufGUefLXPvXSe3MU5KGSNaoJ9KfDqhiKcLV6apqKNSjFt2uFvrpnv7tmOOvL+CvOezGUfjHHP3faKDO7d2/Vu/EN+7INfvSOqDa/PzTOkWP0E+SyFCYyY3k/Oy8Grh7OW8xD28yEPXTTbG+orWRb3s5E5CrnKeHH/3bUHjF+v7kd5LQ+JfX/qPE5NSwQ+7QYMOSFEfO89CoOZZoO//0tui7DvTQPXu45vj4+FSr6lGAn/z8/P7xqJ6pdPzkf0XF8f3pif7qjP3meoN5Zf7le/7nesCMDLiJJ6dMJP3qkv7tl5xQHf7phKJbK9nDU40+GN63VvzmgdqqGme69ei7EP3ogf7ogP3sjsaYKfPTUPPLM/fOM3+dkP7voHbR+f3LG3rR9/zYSbyIDojQ/LFwNLR1N9yuGJtNF//73JBAGf3xo2LP9v3gZPrdYbeDCf///yH5BAEAAP8ALAAAAAAWABYAAAj/AP8JHEjDBgZyT4phsEFjoEOHPk7cYabozwcY+s7hOSHr4UAMPuZEK7dBRwodneCwoGMMg0cM6biAYLGBWJAPVFJoqwYCmgmXA2eYyEaAhAgNPwDg+wBkA5wMBJiYmDEQBzUXBDKYawckD4AgIzyQIEHAxQAcAltYEXUm0SFwC5DCGMFpgREphXBxGNLi3wpBHKKAsmAhgwYNHsKK6NHDkCEOU1b8e+HMjqQbkKBEyiCNm4hwpHxNuhHvCp8X/1QoOPWFSAMhDapkGPCrTyYh97wISJVAxT8e/Uzt2ZJAga4dFkDwq8NgRwBbzdQF4PHPgbdFwRAJKMGdsHcLjWY9xcuixsE/Soxa7XuEoL379wimhXHH6tYyVbl4KRkGxk+IODHEgEQIIRRBSxLwpDGOMsiwgUoXq9RCQSwSSPDAhRXCQgEZycyzixll9DLKMWIE8k0FKKaYIiFYsNOGHJig4IQw9oTyyTYHXKDjjhdMcEkp62yiBwoDyeDKO54cMUEEEGADQQQT0ONGAWtY45EMlhiAhiOvdJOPFvU0UUAB4izhkUA5XKMJIICMaUANNbwBzBhnOpQDOoN0oKc/8lRS55+AChQQADs=' );
    header( 'Content-Type: image/gif' );
    header( 'Content-Length: ' . strlen( $imgData ) );
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( "Pragma: no-cache" );
    header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() ) );
    exit( $imgData );
}


/**
 * 获取HEAD，带超时功能，时间单位为秒
 *
 * @param $url
 * @param int $timeout
 *
 * @return array|mixed
 */
function get_url_headers( $url, $timeout = 10 ) {
    $ch = curl_init();

    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_HEADER, true );
    curl_setopt( $ch, CURLOPT_NOBODY, true );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );

    $data = curl_exec( $ch );
    $data = preg_split( '/\n/', $data );

    $data = array_filter( array_map( function ( $data ) {
        $data = trim( $data );
        if ( $data ) {
            $data   = preg_split( '/:\s/', trim( $data ), 2 );
            $length = count( $data );
            switch ( $length ) {
                case 2:
                    return array( $data[0] => $data[1] );
                    break;
                case 1:
                    return $data;
                    break;
                default:
                    break;
            }
        }
    }, $data ) );

    sort( $data );

    foreach ( $data as $key => $value ) {
        $itemKey = array_keys( $value )[0];
        if ( is_int( $itemKey ) ) {
            $data[ $key ] = $value[ $itemKey ];
        } elseif ( is_string( $itemKey ) ) {
            $data[ $itemKey ] = $value[ $itemKey ];
            unset ( $data[ $key ] );
        }
    }

    return $data;
}

/**
 * 启动脚本
 */
function bootstrap() {
    if ( isset( $_REQUEST['d'] ) ) {
        // 解决诸如 http://{{your_domain}}/?d=http://*.gravatar.com/avatar/{hash}?s=32&s=32
        if ( strpos( $_REQUEST['d'], 'gravatar.com' ) ) {
            $req = explode( 'gravatar.com/avatar/', $_REQUEST['d'] );
            if ( count( $req ) === 2 ) {
                $req = strtolower( $req[1] );
                if ( strpos( $req, '?s=' ) ) {
                    $req = explode( '?s=', $req );
                    process( $req[0], $req[1] );
                } else {
                    showError();
                }
            } else {
            }
        }
        showError();
    }
    // 非32位MD5不进行响应
    if ( isset( $_REQUEST['r'] ) && strlen( $_REQUEST['r'] ) == 32 ) {
        if ( isset( $_REQUEST['s'] ) && is_numeric( $_REQUEST['s'] ) ) {
            process( $_REQUEST['r'], $_REQUEST['s'] );
        } else {
            process( $_REQUEST['r'] );
        }
    } else {
        showError();
    }
}

/**
 * 服务器重写规则
 */
function htaccess() {

    $ableRewrite = false;
    $isNginx = false;
    /** 检查是否支持路径重写 */
    if ( isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
        if ( strpos( $_SERVER['SERVER_SOFTWARE'], 'nginx' ) !== false ) {
            $ableRewrite = true;
            $isNginx = true;
        }
    } else {
        $subMod = 'mod_rewrite';
        if ( function_exists( 'apache_get_modules' ) ) {
            $mods = apache_get_modules();
            if ( in_array( $subMod, $mods ) ) {
                $ableRewrite = true;
            }
        } elseif ( function_exists( 'phpinfo' ) && false === strpos( ini_get( 'disable_functions' ), 'phpinfo' ) ) {
            ob_start();
            phpinfo( 8 );
            $phpinfo = ob_get_clean();
            if ( false !== strpos( $phpinfo, $subMod ) ) {
                $ableRewrite = true;
            }
        }
    }

    if ( $ableRewrite ) {
        if ( !$isNginx ) {

            $rules = <<<EOF
# apache access rule for avatar by soulteary

<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType image/jpeg "access plus 3 year"
  ExpiresByType image/png  "access plus 3 year"
  ExpiresByType text/html  "access plus 3 year"
</IfModule>

<IfModule mod_headers.c>
  FileETag None
  Header unset ETag
  Header unset Server
  Header unset X-Powered-By
  #if you wish, you can add a long time expires
  #Header set Expires "Tue, 08 Dec 2020 20:02:54 GMT"
  #show debug info
  #Header set Cost-Time "Cost %D microseconds for Apache to serve this request."
</IfModule>

<IfModule mod_deflate.c>
AddOutputFilter DEFLATE png jpg html php
</IfModule>

<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule (\w{32})\/?(\d+)?$ /avatar/index.php?r=$1&s=$2 [L]
</IfModule>
EOF;

            createHtaccess( '[主要目录]', ScriptDir, '.htaccess', $rules );

            $subRules = '# apache access rule for avatar by soulteary' . "\n\n";
            $subRules .= 'RewriteEngine on' . "\n";
            $subRules .= 'RewriteCond %{HTTP_REFERER} !^https?:\/\/([A-Za-z0-9.-]{2,})?(' . PrivateDomain . ')(:\d+)? [NC]' . "\n";
            $subRules .= 'RewriteRule .* http://www.baidu.com/s?wd=妈妈说不要盗链 [R,NC,L]' . "\n";

            createHtaccess( '[缓存目录]', ScriptDir . 'cache/', '.htaccess', $subRules );

            createHtaccess( '[占位图目录]', ScriptDir . 'placeholder/', '.htaccess', $subRules );

        }else{

            die("请参考文档搞定Nginx的配置。");

        }
    } else {
        die( "请确认服务器支持重写URL。" );
    }
    exit;
}

/**
 * 创建规则文件
 *
 * @param $fileDesc
 * @param $dir
 * @param $file
 * @param $rules
 */
function createHtaccess( $fileDesc, $dir, $file, $rules ) {
    $file = $dir . $file;
    if ( is_writable( $dir ) ) {
        if ( file_exists( $file ) ) {
            unlink( $file );
        }
        $handle = @fopen( $file, 'w' );
        if ( ! $handle ) {
            die( "请确定以下文件可以被读写：\n" . $file );
        } else {

            fwrite( $handle, $rules );
            fclose( $handle );
            echo( $fileDesc . "规则写入文件成功。\n" );
        }
    } else {
        die( "请确认该路径可以写入：" . $file );
    }

}


if ( isset( $_REQUEST['token'] ) && isset( $_REQUEST['method'] ) && $_REQUEST['token'] == Token ) {
    switch ( $_REQUEST['method'] ) {
        case 'init':
            htaccess();
            break;
    }

}


/** 仅允许指定域名引用资源 **/
if ( PrivateMode ) {
    if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
        $REGEXP = '/^https?:\/\/([A-Za-z0-9.-]{2,})?(' . PrivateDomain . ')(:\d+)?\//i';
        if ( preg_match( $REGEXP, $_SERVER['HTTP_REFERER'] ) ) {
            bootstrap();
        } else {
            die( "Bye~" );
        }
    }
} else {
    bootstrap();
}
exit;