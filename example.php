<?php
include 'PDFInfo.php';

$p = new PDFInfo;
$p->load('D:/tmp/test.pdf');

echo $p->author;
echo $p->title;
echo $p->pages;
