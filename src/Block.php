<?php


class Block
{
    private $height;
    private $hash;
    private $prevHash;
    private $pubKey; //Miner's public key
    private $time;
    private $transactions = [];
    private $nonce;
    private $signature;

    public function getHeight()
    {
        return $this->height;
    }

    public function setHeight($height)
    {
        $this->height = intval($height);
    }

    public function getHash()
    {
        return $this->hash;
    }

    public function getPrevHash()
    {
        return $this->prevHash;
    }

    public function setPrevHash($prevHash)
    {
        $this->prevHash = strval($prevHash);
    }

    public function getPubKey()
    {
        return base64_decode($this->pubKey);
    }

    public function setPubKey($pubKey)
    {
        $this->pubKey = base64_encode($pubKey);
    }

    public function getTime()
    {
        return $this->time;
    }

    public function setTime($time)
    {
        $this->time = intval($time);
    }

    public function getTransactions()
    {
        return $this->transactions;
    }

    public function setTransactions(array $transactions)
    {
        $this->transactions = $transactions;
    }

    public function getNonce()
    {
        return $this->nonce;
    }

    public function setNonce($nonce)
    {
        $this->nonce = intval($nonce);
    }

    public function getSignature()
    {
        return base64_decode($this->signature);
    }

    public function getSignatureBase64()
    {
        return $this->signature;
    }

    public function setSignature($signature)
    {
        $this->signature = base64_encode($signature);
    }

    public function setSignatureBase64($signature)
    {
        $this->signature = strval($signature);
    }

    public function getTransactionsAsString()
    {
        $transactions = [];
        foreach ($this->getTransactions() as $trx) {
            $trxAsStr = strval($trx);
            if ($trxAsStr === false) {
                throw new Exception("Can't cast transaction to string");
            }
            $transactions[] = $trxAsStr;
        }

        return json_encode($transactions);
    }

    public function setTransactionsFromString($string)
    {
        $transactions = json_decode($string, true);
        $result = [];

        foreach ($transactions as $trxAsStr) {
            $result[] = Transaction::createFromString($trxAsStr);

        }
        $this->setTransactions($result);
    }

    public function generateHash()
    {
        if (!$this->height || !$this->prevHash || !$this->pubKey || !$this->time || $this->nonce === null) {
            throw new Exception("All fields must be filled");
        }

        $this->hash = hash('sha256', sprintf(
                '%d %s %s %d %s %d',
                $this->height,
                $this->prevHash,
                $this->pubKey,
                $this->time,
                $this->getTransactionsAsString(),
                $this->nonce
            )
        );
    }

    public function sign($privateKey)
    {
        $this->generateHash();

        openssl_sign(sprintf(
            '%d %s %s %s %d %s %d',
            $this->height,
            $this->hash,
            $this->prevHash,
            $this->pubKey,
            $this->time,
            $this->getTransactionsAsString(),
            $this->nonce
        ),
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256
        );

        $this->setSignature($signature);
    }

    public function isValid()
    {
        $pubKey = $this->getPubKey();

        foreach ($this->getTransactions() as $transaction) {
            if (!$transaction->isValid()) {
                return false;
            }
        }

        return 1 === openssl_verify(sprintf(
                '%d %s %s %s %d %s %d',
                $this->height,
                $this->hash,
                $this->prevHash,
                $this->pubKey,
                $this->time,
                $this->getTransactionsAsString(),
                $this->nonce
            ),
                $this->getSignature(),
                $pubKey,
                OPENSSL_ALGO_SHA256
            );
    }

    public function __toString()
    {
        if ($this->isValid()) {
            //beware to use json in other languages (e.g. python). Fields can be reordered.
            return base64_encode(json_encode([
                'height' => $this->getHeight(),
                'hash' => $this->getHash(),
                'prevHash' => $this->getPrevHash(),
                'pubKey' => $this->getPubKey(),
                'time' => $this->getTime(),
                'transactions' => $this->getTransactionsAsString(),
                'nonce' => $this->getNonce(),
                'signature' => $this->getSignatureBase64(), //json_encode won't work with binary string, so use base64 encoded
            ]));
        } else {
            return false;
        }
    }

    public static function createFromString($string)
    {
        $data = json_decode(base64_decode($string), true);

        if (!$data['height'] || !$data['hash'] || !$data['prevHash'] || !$data['pubKey'] ||
            !$data['time'] || !$data['transactions'] || !$data['nonce'] || !$data['signature']) {
            throw new Exception("There's no enough data to populate the block");
        }

        $block = new self();
        $block->setHeight($data['height']);
        $block->setPrevHash($data['prevHash']);
        $block->setPubKey($data['pubKey']);
        $block->setTime($data['time']);
        $block->setNonce($data['nonce']);
        $block->setSignatureBase64($data['signature']);

        $block->setTransactionsFromString($data['transactions']);
        $block->generateHash();

        if ($block->getHash() !== $data['hash']) {
            throw new Exception("Expected id of block don't match with generated");
        }

        if (!$block->isValid()) {
            throw new Exception("Block is not valid");
        }

        return $block;
    }

    public static function createGenesis($pubKey, $privKey, $transactions, $nonce)
    {
        $block = new self();

        $block->setHeight(1);
        $block->setPrevHash('genesis');
        $block->setPubKey($pubKey);
        $block->setTime(time());
        $block->setNonce($nonce);
        $block->setTransactions($transactions);

        $block->generateHash();
        $block->sign($privKey);

        if (!$block->isValid()) {
            throw new Exception("Block is not valid");
        }

        return $block;
    }

    public static function create(Block $prevBlock, $pubKey, $privKey, $transactions, $nonce)
    {
        if (!$prevBlock->isValid()) {
            throw new Exception("The previous block is not valid");
        }

        $block = new self();

        $block->setHeight($prevBlock->getHeight() + 1);
        $block->setPrevHash($prevBlock->getHash());
        $block->setPubKey($pubKey);
        $block->setTime(time());
        $block->setNonce($nonce);
        $block->setTransactions($transactions);

        $block->generateHash();
        $block->sign($privKey);

        if (!$block->isValid()) {
            throw new Exception("Block is not valid");
        }

        return $block;
    }
}