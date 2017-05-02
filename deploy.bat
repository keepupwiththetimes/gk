#!/bin/bash

# 本文件用于在正式环境部署本项目用
# 需要提供一个版本号作为参数

echo 'Usage: ./deploy.bat version_num  '
echo 'Example: ./deploy.bat 35'
echo
echo

#check if version number provided.
var=$1
if [ "$var" -eq "$var" ] 2>/dev/null; then
  echo 'version number set to '$1
else
  echo 'version number is not an integer, please try again'
  exit;
fi

# 把 Public/Home/Resource 修改成 Public/Home/Resource + EDITION_NUM
echo 'change Public/Home/Resource folder name'
mv /var/www/html/wxzspfse/Public/Home/Resource  /var/www/html/wxzspfse/Public/Home/Resource$1


#cd /var/www/html/wxzspfse
chmod -R 755 /var/www/html/wxzspfse
chmod -R 755 /var/www/html/wxzspfse/*

echo 'modify the index.php file'
sed -i.bak 's/^.*define.*EDITION_NUM.*$/define\("EDITION_NUM"\, '"$1"'\);/g' /var/www/html/wxzspfse/index.php   # save the original copy to index.php.bak
sed -i.tmp 's/^.*define.*DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER.*TRUE.*$/define\("DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER" \, FALSE\);/g' /var/www/html/wxzspfse/index.php
rm /var/www/html/wxzspfse/index.php.tmp  #remove the temporry file


#把 Application/Runtime 目录设成任意人可读写
echo 'change Application/Runtime to 777'
chmod 777 /var/www/html/wxzspfse/Application/Runtime

echo 'setup symbolic link for folders storing static files'
ln -s /var/www/html/ltb_static_files/zhWelcome/   /var/www/html/wxzspfse/Public/Home/Default/Image/zsh2.0/
ln -s /var/www/html/ltb_static_files/product/   /var/www/html/wxzspfse/Public/Home/Default/Image/
ln -s /var/www/html/ltb_static_files/Audio/   /var/www/html/wxzspfse/Public/Home/Default/

#echo 'restart web server'
#/etc/init.d/nginx restart

 echo 
 echo
