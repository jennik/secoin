<?php
include_once 'src/bootstrap.php';

$repo = Repository::getInstance();
$privKey = file_get_contents(PROJ_ROOT . 'data/priv.key');
$pubKey = file_get_contents(PROJ_ROOT . 'data/pub.key');
$nonce = 0;
$difficulty = 3; //in our example it is static

while (1) {
    $nonce++;
    $lastBlock = $repo->getLastBlock();
    $transactions = $repo->get10TransactionsFromPool();

    if ($lastBlock === null) {
        $block = Block::createGenesis($pubKey, $privKey, $transactions, $nonce);
    } else {
        $block = Block::create($lastBlock, $pubKey, $privKey, $transactions, $nonce);
    }

    if (substr($block->getHash(), 0, $difficulty) === str_repeat('0', $difficulty)) {
        echo "Mined new block with hash: {$block->getHash()}\n";
        $nonce = 0;
        $repo->addBlock($block);
        //save to db
        // send to others
    }
}

//$trxs = Repository::getInstance()->get10TransactionsFromPool();

