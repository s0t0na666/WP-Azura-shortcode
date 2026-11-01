<?php
if(!defined('ABSPATH'))exit;
final class AZNS_Logger{
 static function dir(){return trailingslashit(wp_upload_dir()['basedir']).'azuracast-logs';}
 static function init(){ $d=self::dir();if(wp_mkdir_p($d)){if(!file_exists("$d/index.php"))@file_put_contents("$d/index.php","<?php // no direct access\n");if(!file_exists("$d/.htaccess"))@file_put_contents("$d/.htaccess","Deny from all\n");}}
 static function log($level,$message,$context=[]){self::init();$d=self::dir();if(!is_writable($d))return;unset($context['api_key'],$context['authorization']);$row=['time'=>gmdate('c'),'level'=>sanitize_key($level),'message'=>sanitize_text_field($message),'context'=>$context];@file_put_contents("$d/azuracast.log",wp_json_encode($row).PHP_EOL,FILE_APPEND|LOCK_EX);}
 static function tail(){ $f=self::dir().'/azuracast.log';if(!is_readable($f))return '';return implode("\n",array_slice(file($f,FILE_IGNORE_NEW_LINES)?:[],-150));}
 static function clear(){ $f=self::dir().'/azuracast.log';if(is_file($f))@unlink($f);}
}
