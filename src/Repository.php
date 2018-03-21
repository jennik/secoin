<?php


class Repository
{
    private $db;

    private function __construct()
    {
        $this->db = new PDO('sqlite:' . PROJ_ROOT . 'data/db.sqlite3');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_TIMEOUT, 120);
    }

    public static function getInstance()
    {
        static $repo = null;
        if (!$repo) {
            $repo = new self();
        }

        return $repo;
    }

    // Transactions

    public function addTransaction(Transaction $transaction)
    {
        static $stmt = null;
        if (!$stmt) {
            $stmt = $this->db->prepare('INSERT OR IGNORE INTO transactions_pool 
            (id, "from", "to", amount, signature, time)
            VALUES
            (:id, :from, :to, :amount, :signature, :time)
            ');
        }

        if (!$transaction->isValid()) {
            return;
        }

        $stmt->bindValue('id', $transaction->getId());
        $stmt->bindValue('from', $transaction->getFrom());
        $stmt->bindValue('to', $transaction->getTo());
        $stmt->bindValue('amount', $transaction->getAmount());
        $stmt->bindValue('signature', $transaction->getSignatureBase64());
        $stmt->bindValue('time', $transaction->getTime());

        $stmt->execute();
        $stmt->closeCursor();
    }

    public function getTransactionById($id)
    {
        static $stmt = null;
        if (!$stmt) {
            $stmt = $this->db->prepare('SELECT * FROM transactions_pool WHERE id = :id');
        }

        $stmt->bindValue('id', $id);
        $stmt->execute();
        $data = $stmt->fetch();
        $stmt->closeCursor();

        if (!$data) {
            return null;
        }

        $transaction = new Transaction();
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

    public function get10TransactionsFromPool()
    {
        static $stmt = null;
        if (!$stmt) {
            $stmt = $this->db->prepare('SELECT id FROM transactions_pool LIMIT 10');
        }

        $res = [];

        $stmt->execute();
        $trIds = $stmt->fetchAll();
        $stmt->closeCursor();

        if (!$trIds) {
            return $res;
        }

        foreach ($trIds as $trId) {
            $res[] = $this->getTransactionById($trId['id']);
        }

        return $res;
    }

    public function deleteTransactionFromPool($id)
    {
        static $stmt = null;
        if (!$stmt) {
            $stmt = $this->db->prepare('DELETE FROM transactions_pool WHERE id = :id');
        }

        $stmt->bindValue('id', $id);
        $stmt->execute();
        $stmt->closeCursor();
    }

    // Blocks

    public function getLastBlock()
    {
        static $stmt = null;
        if (!$stmt) {
            $stmt = $this->db->prepare('SELECT * FROM blocks ORDER BY height DESC LIMIT 1');
        }

        $stmt->execute();
        $data = $stmt->fetch();
        $stmt->closeCursor();

        if (!$data) {
            return null;
        }

        $block = new Block();
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

    public function addBlock(Block $block)
    {
        static $stmt = null;
        if ($stmt === null) {
            $stmt = $this->db->prepare('INSERT OR IGNORE INTO blocks (
                height,
                "hash",
                prevHash,
                pubKey,
                "time",
                transactions,
                nonce,
                signature
            ) VALUES (
                :height,
                :hash,
                :prevHash,
                :pubKey,
                :time,
                :transactions,
                :nonce,
                :signature
            )');
        }

        if (!$block->isValid()) {
            return;
        }

        $stmt->bindValue('height', $block->getHeight());
        $stmt->bindValue('hash', $block->getHash());
        $stmt->bindValue('prevHash', $block->getPrevHash());
        $stmt->bindValue('pubKey', $block->getPubKey());
        $stmt->bindValue('time', $block->getTime());
        $stmt->bindValue('transactions', $block->getTransactionsAsString());
        $stmt->bindValue('nonce', $block->getNonce());
        $stmt->bindValue('signature', $block->getSignatureBase64());

        $stmt->execute();
        $stmt->closeCursor();

        foreach ($block->getTransactions() as $transaction) {
            $this->deleteTransactionFromPool($transaction->getId());
        }
    }
}