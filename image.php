<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';
require 'src/ImageProcessor.php';
require 'src/ImageDownloader.php';
require 'src/Utils.php';

// Initialize the processor
$processor = new ImageProcessor();

// Process request
$processor->handleRequest($_GET);
