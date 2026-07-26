<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
admin_require_auth();

require_once dirname(__DIR__) . '/includes/PdfDocumentGenerator.php';

$id = admin_text($_GET['id'] ?? '', 100);
$document = $id !== '' ? admin_store()->getDocument($id) : null;
if (!$document) {
    http_response_code(404);
    exit('Document not found.');
}

try {
    $generator = new PdfDocumentGenerator(dirname(__DIR__) . '/assets/images/logo-r-pdf.jpg');
    $pdf = $generator->render($document);
} catch (Throwable $error) {
    error_log('RGTS PDF generation failed: ' . $error->getMessage());
    http_response_code(500);
    exit('The PDF could not be generated.');
}

$number = preg_replace('/[^A-Za-z0-9._-]/', '-', (string)($document['number'] ?? 'Resplendent-Document')) ?: 'Resplendent-Document';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $number . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: no-store, private');
echo $pdf;
