### RISESUN (荣盛）点单登录

### windows本地部署
1. 安装java 环境（建议安装java1.8），不会部署java环境请找**度娘**
2. 配置java环境变量
3. 复制当前目录下的SecurityHelper.jar到java安装目录的/jre/lib/ext
4. 命令行进入JavaBridge.jar所在目录，运行javabridge服务：java -jar JavaBridge.jar HTTP_LOCAL:8080 或者 java -jar JavaBridge.jar SERVLET_LOCAL:8080
5. 执行test.php（php test.php）验证SecurityHelper.jar是否引入成功

### linux环境部署
1. 查看java安装目录（如何查看java安装目录自行百度）
2. 复制SecurityHelper.jar到到java安装目录的/jre/lib/ext
#### 复制PBE.jar到/usr/java/jdk1.8.0_144/jre/lib/ext（linux）

