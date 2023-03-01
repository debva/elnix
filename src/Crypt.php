<?php

namespace Debva\Elnix;

class Crypt
{
    private $key;

    public function __construct()
    {
        $this->key = getenv('APP_KEY')
            ? getenv('APP_KEY')
            : die('Environment APP_KEY not defined');
    }

    public function bcrypt($data)
    {
        return password_hash("{$data}._.{$this->key}", PASSWORD_BCRYPT);
    }

    public function verify($data, $hashedData)
    {
        return password_verify("{$data}._.{$this->key}", $hashedData);
    }

    public function encrypt($data)
    {
        $ivSize = openssl_cipher_iv_length('AES-256-CBC');
        $iv = openssl_random_pseudo_bytes($ivSize);

        $encryptedData = openssl_encrypt($data, 'AES-256-CBC', $this->key, OPENSSL_RAW_DATA, $iv);

        $encryptedDataWithIV = $iv . $encryptedData;

        $dataBytes = str_split($encryptedDataWithIV);
        $keyBytes = str_split($this->key);

        $encryptedDataBytes = [];
        $keyIndex = 0;
        foreach ($dataBytes as $dataByte) {
            $keyByte = $keyBytes[$keyIndex % count($keyBytes)];
            $encryptedDataBytes[] = $dataByte ^ $keyByte;
            $keyIndex++;
        }

        $encryptedData = implode($encryptedDataBytes);

        $encodedEncryptedData = base64_encode($encryptedData);

        return $encodedEncryptedData;
    }

    public function decrypt($encryptedData)
    {
        $decodedEncryptedData = base64_decode($encryptedData);

        $encryptedDataBytes = str_split($decodedEncryptedData);

        $keyBytes = str_split($this->key);

        $decryptedDataBytes = [];
        $keyIndex = 0;
        foreach ($encryptedDataBytes as $encryptedDataByte) {
            $keyByte = $keyBytes[$keyIndex % count($keyBytes)];
            $decryptedDataBytes[] = $encryptedDataByte ^ $keyByte;
            $keyIndex++;
        }

        $decryptedData = implode($decryptedDataBytes);

        $ivSize = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($decryptedData, 0, $ivSize);

        $encryptedData = substr($decryptedData, $ivSize);

        $decryptedData = openssl_decrypt($encryptedData, 'AES-256-CBC', $this->key, OPENSSL_RAW_DATA, $iv);

        return $decryptedData;
    }
}
