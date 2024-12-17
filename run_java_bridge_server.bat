set dateStr=%date:~0,10%

java -Xbootclasspath/a:./system/java/wynca/commons-codec-1.15.jar;./system/java/wynca/wynca.jar;./system/java/gofluent/gofluent-1.0-SNAPSHOT.jar -jar system/java/JavaBridge.jar HTTP_LOCAL:8080 3