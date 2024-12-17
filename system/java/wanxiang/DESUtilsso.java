package com.wanxiang;

import java.io.IOException;
import java.security.SecureRandom;

import javax.crypto.Cipher;
import javax.crypto.SecretKey;
import javax.crypto.SecretKeyFactory;
import javax.crypto.spec.DESKeySpec;

import sun.misc.BASE64Decoder;
import sun.misc.BASE64Encoder;

/**
 * DES加密 解密算法
 *
 * @author lifq
 * @date 2015-3-17 上午10:12:11
 */
public class DESUtilsso {

    private final static String DES = "DES";
    private final static String ENCODE = "UTF-8";
    private final static String defaultKey = "1IRuOLamuFM6zxZv";

    public static void main(String[] args) throws Exception {
        String data = "WX0001543";
        // System.err.println(encrypt(data, key));
        // System.err.println(decrypt(encrypt(data, key), key));
        //System.out.println(encrypt(data,"CF046739C7BD4E1390D3D052D3AF059E"));
        System.out.println(decrypt("d5fa9f11a49583975811133ab28a9fccd5bb410f93e6af0a66201c7c0a3ee9f1f3d6a5e2b988f555cac4f9dba8396641405be4bcf67012ff9ed713e9f85b0835","CF046739C7BD4E1390D3D052D3AF059E"));

    }

    /**
     * 使用 默认key 加密
     *
     * @return String
     * @author lifq
     * @date 2015-3-17 下午02:46:43
     */
    public static String encrypt(String data) throws Exception {
        byte[] bt = encrypt(data.getBytes(ENCODE), defaultKey.getBytes(ENCODE));
//        String strs = new BASE64Encoder().encode(bt);
        String strs = bytesToHexString(bt);
        return strs;
    }

    /**
     * 使用 默认key 解密
     *
     * @return String
     * @author lifq
     * @date 2015-3-17 下午02:49:52
     */
    public static String decrypt(String data) throws IOException, Exception {
        if (data == null) {
            return null;
        }
        BASE64Decoder decoder = new BASE64Decoder();
//        byte[] buf = decoder.decodeBuffer(data);
        byte[] buf = hexToByte(data);
        byte[] bt = decrypt(buf, defaultKey.getBytes(ENCODE));
        return new String(bt, ENCODE);
    }

    /**
     * Description 根据键值进行加密
     *
     * @param data
     * @param key 加密键byte数组
     * @return
     * @throws Exception
     */
    public static String encrypt(String data, String key) throws Exception {
        byte[] bt = encrypt(data.getBytes(ENCODE), key.getBytes(ENCODE));
        //        String strs = new BASE64Encoder().encode(bt);
        String strs = bytesToHexString(bt);
        return strs;
    }

    /**
     * Description 根据键值进行解密
     *
     * @param data
     * @param key 加密键byte数组
     * @return
     * @throws IOException
     * @throws Exception
     */
    public static String decrypt(String data, String key) throws IOException, Exception {
        if (data == null) {
            return null;
        }
        BASE64Decoder decoder = new BASE64Decoder();
        //        byte[] buf = decoder.decodeBuffer(data);
        byte[] buf = hexToByte(data);
        byte[] bt = decrypt(buf, key.getBytes(ENCODE));
        return new String(bt, ENCODE);
    }

    /**
     * Description 根据键值进行加密
     *
     * @param data
     * @param key 加密键byte数组
     * @return
     * @throws Exception
     */
    private static byte[] encrypt(byte[] data, byte[] key) throws Exception {
        // 生成一个可信任的随机数源
        SecureRandom sr = new SecureRandom();

        // 从原始密钥数据创建DESKeySpec对象
        DESKeySpec dks = new DESKeySpec(key);

        // 创建一个密钥工厂，然后用它把DESKeySpec转换成SecretKey对象
        SecretKeyFactory keyFactory = SecretKeyFactory.getInstance(DES);
        SecretKey securekey = keyFactory.generateSecret(dks);

        // Cipher对象实际完成加密操作
        Cipher cipher = Cipher.getInstance(DES);

        // 用密钥初始化Cipher对象
        cipher.init(Cipher.ENCRYPT_MODE, securekey, sr);

        return cipher.doFinal(data);
    }

    /**
     * Description 根据键值进行解密
     *
     * @param data
     * @param key 加密键byte数组
     * @return
     * @throws Exception
     */
    private static byte[] decrypt(byte[] data, byte[] key) throws Exception {
        // 生成一个可信任的随机数源
        SecureRandom sr = new SecureRandom();

        // 从原始密钥数据创建DESKeySpec对象
        DESKeySpec dks = new DESKeySpec(key);

        // 创建一个密钥工厂，然后用它把DESKeySpec转换成SecretKey对象
        SecretKeyFactory keyFactory = SecretKeyFactory.getInstance(DES);
        SecretKey securekey = keyFactory.generateSecret(dks);

        // Cipher对象实际完成解密操作
        Cipher cipher = Cipher.getInstance(DES);

        // 用密钥初始化Cipher对象
        cipher.init(Cipher.DECRYPT_MODE, securekey, sr);

        return cipher.doFinal(data);
    }

    /**
     * 将byte数组转换为16进制字符串
     *
     * @param
     * @author BinQi.Dai 2020-04-17 13:30
     * @return
     */
    public static String bytesToHexString(byte[] src) {
        StringBuilder stringBuilder = new StringBuilder("");
        if (src == null || src.length <= 0) {
            return null;
        }
        for (int i = 0; i < src.length; i++) {
            int v = src[i] & 0xFF;
            String hv = Integer.toHexString(v);
            if (hv.length() < 2) {
                stringBuilder.append(0);
            }
            stringBuilder.append(hv);
        }
        return stringBuilder.toString();
    }

    /**
     * 将16进制字符串转换为byte数组
     *
     * @param
     * @author BinQi.Dai 2020-04-17 13:30
     * @return
     */
    public static byte[] hexToByte(String hex){
        hex = hex.replace(" ","");
        int byteLength = hex.length()/2;
        byte[] bytes = new byte[byteLength];
        int m = 0;
        int n = 0;
        for(int i = 0; i<byteLength;i++){
            m = i*2+1;
            n = m+1;
            int intHex = Integer.decode("0x"+hex.substring(i * 2, m) + hex.substring(m, n));
            bytes[i] = Byte.valueOf((byte) intHex);
        }
        return bytes;
    }
}
