#!/usr/bin/env bash

dateStr=$(date "+%Y-%m-%d")

#启动java bridge服务
#nohup java -jar system/java/JavaBridge.jar HTTP_LOCAL:8080 3 /tmp/java-bridge.log > /tmp/java-bridge-svc.log 2>&1 &
nohup java -Xbootclasspath/p:./system/java/wynca/commons-codec-1.15.jar:./system/java/wynca/wynca.jar:./system/java/gofluent/gofluent-1.0-SNAPSHOT.jar -jar system/java/JavaBridge.jar HTTP_LOCAL:8080 3 /tmp/java-bridge."${dateStr}".log > /tmp/java-bridge-svc-"${dateStr}".log 2>&1 &
