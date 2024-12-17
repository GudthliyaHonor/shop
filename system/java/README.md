### 运行javabridge服务
···
java -jar JavaBridge.jar HTTP_LOCAL:8080 或者 java -jar JavaBridge.jar SERVLET_LOCAL:8080
···

#### 帮助
···
java -jar JavaBridge.jar -h
···

结果：
···
PHP/Java Bridge version 7.2.1
Copyright (C) 2003, 2006 Jost Boekemeier and others.
This is free software; see the source for copying conditions.  There is NO
warranty; not even for MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
Usage: java -jar JavaBridge.jar [SOCKETNAME LOGLEVEL LOGFILE]
SOCKETNAME is one of INET_LOCAL, INET, HTTP_LOCAL, HTTP, HTTPS_LOCAL, HTTPS

Example 1: java -jar JavaBridge.jar
Example 2: java -jar JavaBridge.jar HTTP_LOCAL:8080 3 JavaBridge.log
Example 3: java -Djavax.net.ssl.keyStore=mySrvKeystore -Djavax.net.ssl.keyStorePassword=YOURPASSWD -jar JavaBridge.jar HTTPS:8443 3 JavaBridge.log
The certificate for example 3 can be created with e.g.: jdk1.6.0/bin/keytool -keystore mySrvKeystore -genkey -keyalg RSA

Influential system properties: threads, daemon, php_exec, default_log_file, default_log_level, base.
Example: java -Djava.awt.headless="true" -Dphp.java.bridge.threads=50 -Dphp.java.bridge.base=/usr/lib/php/modules -Dphp.java.bridge.php_exec=/usr/local/bin/php-cgi -Dphp.java.bridge.default_log_file= -Dphp.java.bridge.default_log_level=5 -jar JavaBridge.jar
Example: java -Dphp.java.bridge.daemon="true" -jar JavaBridge.jar
···

### 一点正式ext目录
/usr/lib/jvm/java-1.8.0-openjdk-1.8.0.275.b01-0.el7_9.x86_64/jre/lib/ext/