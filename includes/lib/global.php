<?php

function __require($require) {
    switch ($require) {
        case "config":
            require_once(__DIR__."/config.php");
            break;
        case "auth":
            require_once(__DIR__."/auth.php");
            break;
        case "vendor/geophp":
            include_once(__DIR__."/../../vendor/geoPHP/geoPHP.inc");
            break;
        case "vendor/qrcode":
            include_once(__DIR__."/../../vendor/qr-code/src/Endroid/QrCode/QrCode.php");
            include_once(__DIR__."/../../vendor/qr-code/src/Endroid/QrCode/Exceptions/DataDoesntExistException.php");
            include_once(__DIR__."/../../vendor/qr-code/src/Endroid/QrCode/Exceptions/ImageFunctionUnknownException.php");
            include_once(__DIR__."/../../vendor/qr-code/src/Endroid/QrCode/Exceptions/ImageSizeTooLargeException.php");
            include_once(__DIR__."/../../vendor/qr-code/src/Endroid/QrCode/Exceptions/VersionTooLargeException.php");
            break;
        case "vendor/sqrl":
            include_once(__DIR__."/../../vendor/sqrl/src/Trianglman/Sqrl/EcEd25519NonceValidator.php");
            include_once(__DIR__."/../../vendor/sqrl/src/Trianglman/Sqrl/Ed25519NonceValidator.php");
            include_once(__DIR__."/../../vendor/sqrl/src/Trianglman/Sqrl/NonceValidatorInterface.php");
            include_once(__DIR__."/../../vendor/sqrl/src/Trianglman/Sqrl/SodiumNonceValidator.php");
            include_once(__DIR__."/../../vendor/sqrl/src/Trianglman/Sqrl/SqrlConfiguration.php");
            include_once(__DIR__."/../../vendor/sqrl/src/Trianglman/Sqrl/SqrlException.php");
            include_once(__DIR__."/../../vendor/sqrl/src/Trianglman/Sqrl/SqrlGenerate.php");
            include_once(__DIR__."/../../vendor/sqrl/src/Trianglman/Sqrl/SqrlGenerateInterface.php");
            include_once(__DIR__."/../../vendor/sqrl/src/Trianglman/Sqrl/SqrlRequestHandler.php");
            include_once(__DIR__."/../../vendor/sqrl/src/Trianglman/Sqrl/SqrlRequestHandlerInterface.php");
            include_once(__DIR__."/../../vendor/sqrl/src/Trianglman/Sqrl/SqrlStoreInterface.php");
            include_once(__DIR__."/../../vendor/sqrl/src/Trianglman/Sqrl/SqrlStoreStatelessAbstract.php");
            include_once(__DIR__."/../../vendor/sqrl/src/Trianglman/Sqrl/SqrlValidate.php");
            include_once(__DIR__."/../../vendor/sqrl/src/Trianglman/Sqrl/SqrlValidateInterface.php");
            include_once(__DIR__."/../../vendor/sqrl/src/Trianglman/Sqrl/Ed25519/Crypto.php");
            include_once(__DIR__."/../../vendor/sqrl/src/Trianglman/Sqrl/Ed25519/CryptoInterface.php");
            break;
        case "vendor/sparrow":
            include_once(__DIR__."/../../vendor/sparrow/sparrow.php");
            break;
    }
}

?>
