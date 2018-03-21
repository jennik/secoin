<?php


class Transaction
{
    private $id;
    private $from;
    private $to;
    private $amount;
    private $signature;
    private $time;

    public function getId()
    {
        return $this->id;
    }

    public function getFrom()
    {
        return base64_decode($this->from);
    }

    public function setFrom($from)
    {
        $this->from = base64_encode($from);
    }

    public function getTo()
    {
        return $this->to;
    }

    public function setTo($to)
    {
        $this->to = strval($to);
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function setAmount($amount)
    {
        $this->amount = floatval($amount);
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

    public function getTime()
    {
        return $this->time;
    }

    public function setTime($time)
    {
        $this->time = intval($time);
    }

    public function generateId()
    {
        if (!$this->from || !$this->to || !$this->amount || !$this->time) {
            throw new Exception("All fields must be filled");
        }

        $this->id = hash('sha256', sprintf(
                '%s %s %1.5F %d',
                $this->from,
                $this->to,
                $this->amount,
                $this->time
            )
        );
    }

    public function sign($privateKey)
    {
        $this->generateId();

        openssl_sign(sprintf(
                '%s %s %s %1.5F %d',
                $this->id,
                $this->from,
                $this->to,
                $this->amount,
                $this->time
            ),
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256
        );

        $this->setSignature($signature);
    }

    public function isValid()
    {
        $pubKey = $this->getFrom();

        return 1 === openssl_verify(sprintf(
                    '%s %s %s %1.5F %d',
                    $this->id,
                    $this->from,
                    $this->to,
                    $this->amount,
                    $this->time
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
                'id' => $this->getId(),
                'from' => $this->getFrom(),
                'to' => $this->getTo(),
                'amount' => $this->getAmount(),
                'signature' => $this->getSignatureBase64(), //json_encode won't work with binary string, so use base64 encoded
                'time' => $this->getTime(),
            ]));
        } else {
            return false;
        }
    }

    public static function createFromString($string)
    {
        $data = json_decode(base64_decode($string), true);

        if (!$data['id'] || !$data['from'] || !$data['to'] || !$data['amount'] || !$data['signature'] || !$data['time']) {
            throw new Exception("There's no enough data to populate new transaction");
        }

        $transaction = new self();
        $transaction->setFrom($data['from']);
        $transaction->setTo($data['to']);
        $transaction->setAmount($data['amount']);
        $transaction->setSignatureBase64($data['signature']);
        $transaction->setTime($data['time']);

        $transaction->generateId();

        if ($transaction->getId() !== $data['id']) {
            throw new Exception("Expected id of transaction don't match with generated");
        }

        if (!$transaction->isValid()) {
            throw new Exception("Transaction is not valid");
        }

        return $transaction;
    }
}
