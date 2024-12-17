<?php
/**
 * S3/OSS client.
 * User: roy
 * Date: 2018/3/10
 * Time: 14:43
 */

namespace App\Common;


interface CloudClient
{
    // 私有
    const AclPrivate = 'private';
    // 公共读
    const AclPublicRead = 'public-read';
    // 公共读写
    const AclPublicReadWrite = 'public-read-write';
    // 桶公共读，桶内对象公共读
    const AclPublicReadDelivered = 'public-read-delivered';
    // 桶公共读写，桶内对象公共读写
    const AclPublicReadWriteDelivered = 'public-read-write-delivered';


    /**
     * @param string $contentType
     * @return $this
     */
    public function setContentType($contentType = null);

    /**
     * Set starts-with condition for policy.
     *
     * @param string $startsWith
     * @return $this
     */
    public function setStartsWithDir($startsWith = 'usr-dir/');

    /**
     * Set configure for init.
     * @param array $config
     * @return $this
     */
    public function setConfig($config);

    /**
     * Get the current configure.
     * @return array
     */
    public function getConfig();

    /**
     * Get presigned url for the object in S3/OSS.
     *
     * @param string $key Object ID
     * @param int|string $expires
     * @return string
     */
    public function getPresignedUrl($key, $expires = '+7 days');

    /**
     * Get uploading pre-signed url.
     * @param string $objectId
     * @param string|int $expires
     * @param array $options
     * @return string
     */
    public function getPresignedPostUrl($objectId, $expires = '+5 minutes', $options = []);

    /**
     * Generate a presigned url for file uploading.
     *
     * @param string $objectId Object ID, such as Filename
     * @param int|string $expires
     * @return string
     */
    public function getPresignedPostPolicy($objectId, $expires = '+7 days');

    /**
     * Upload file from server.
     * @param string $file Uploading file path
     * @param string $key File name in S3/Oss
     * @param null|array $options
     * @return mixed
     */
    public function uploadFile($file, $key, $options = []);

    /**
     * Multipart uploads.
     *
     * @param string $file File path
     * @param string $key Filename in s3/oss
     * @return mixed
     */
    public function multipartUploadFile($file, $key);

    /**
     * Determines whether or not an object exists by name.
     *
     * @param string $key The key of the object
     * @param null|string $bucket The name of the bucket
     * @param array $options Additional options available in the HeadObject
     *                        operation (e.g., VersionId).
     * @return bool
     */
    public function objectExists($key, $bucket = null, $options = []);


    /**
     * Download the file.
     * @param string $localfile Local file path
     * @param string $object File path in bucket
     * @param string $bucket Bucket name
     * @param string $url Optional. Download url of the file.
     * @return mixed
     */
    public function download($localfile, $object, $bucket = 'talentyun-weike', $url = null);

    /**
     * Download the file via URL.
     * @param string $url File link
     * @return bool
     */
    public function downloadByUrl($url);

    /**
     * Set the page url of upload page.
     * @param string $referer
     * @return $this
     */
    public function setReferer($referer);

    /**
     * Check if the client supports Public-Read Acl.
     *
     * @return boolean
     */
    public function supportPublicReadAcl();

    /**
     * Set the expiration of bucket or bucket folder.
     * @param string $bucket Bucket name
     * @param string $ruleName Rule ID, example: rule0
     * @param string $matchPrefix File prefix, such as `A0/`
     * @param string $timeSpec Days or Date
     * @param int $timeValue Lifecycle time
     * @return void
     */
    public function setExpiration($bucket, $ruleName, $matchPrefix, $timeSpec = \OSS\OssClient::OSS_LIFECYCLE_TIMING_DAYS, $timeValue = 30);

}