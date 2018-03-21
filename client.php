<?php
include_once 'src/bootstrap.php';

$cmd = $argv[1] ?? 'none';

switch ($cmd) {
    case 'create-keys':
        createKeys();
        break;

    case 'get-wallet':
        getWallet();
        break;

    case 'pay':
        $to = $argv[2];
        $amount = floatval($argv[3]);
        pay($to, $amount);
        break;
}


function createKeys()
{
    $config = [
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'private_key_bits' => 512,
    ];

    $res = openssl_pkey_new($config);
    openssl_pkey_export($res, $privateKey);
    $publicKey = openssl_pkey_get_details($res)['key'];

    file_put_contents(PROJ_ROOT . 'data/priv.key', $privateKey);
    file_put_contents(PROJ_ROOT . 'data/pub.key', $publicKey);

    echo "Your keys were generated\n";
}

function getWallet()
{
    $pubKey = file_get_contents(PROJ_ROOT . 'data/pub.key');
    echo hash('ripemd160', $pubKey) . "\n";
}

function pay($to, $amount)
{
    $pubKey = file_get_contents(PROJ_ROOT . 'data/pub.key');
    $privKey = file_get_contents(PROJ_ROOT . 'data/priv.key');

    $transaction = new Transaction();
    $transaction->setFrom($pubKey);
    $transaction->setTo($to); //to is ripemd160 hash of public key of recipient
    $transaction->setAmount($amount);
    $transaction->setTime(time());

    $transaction->sign($privKey);

    if ($transaction->isValid()) {
        echo "Transaction {$transaction->getId()} is created\n";
        Repository::getInstance()->addTransaction($transaction);
        // send to others
    } else {
        echo "Can't validate created transaction";
    }
}

