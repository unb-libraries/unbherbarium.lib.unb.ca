<?php

// Define the input and output file paths using __DIR__
$inputFile = __DIR__ . '/input.html';
$outputFile = __DIR__ . '/output.html';

if (!file_exists($inputFile)) {
    die("Input file not found: $inputFile");
}

$htmlContent = file_get_contents($inputFile);

// Ensure the HTML content has a proper <html><body> structure
$htmlContent = '<!DOCTYPE html><html><body>' . $htmlContent . '</body></html>';

// Load the HTML content into a DOMDocument
$dom = new DOMDocument();
libxml_use_internal_errors(true); // Suppress warnings for malformed HTML
$dom->loadHTML($htmlContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
libxml_clear_errors();

// XPath to find the <a> tags and <li> tags
$xpath = new DOMXPath($dom);

// Loop through the <a> tags with class "note"
$aTags = $xpath->query('//a[@class="note"]');
if ($aTags->length === 0) {
    echo "No <a> tags with class 'note' found.\n";
} else {
    foreach ($aTags as $aTag) {
        $dataValue = $aTag->nodeValue; // Get the content of the <a> tag
        $noteId = $aTag->getAttribute('id'); // Get the id attribute of the <a> tag

        echo "Processing <a> tag with id '$noteId' and value '$dataValue'.\n";

        // Find the corresponding <li> element based on the href of the <a> tag
        $href = $aTag->getAttribute('href');
        $hrefId = ltrim($href, '#'); // Remove the '#' prefix
        $liTag = $xpath->query("//li[@id='$hrefId']")->item(0);

        if ($liTag) {
            echo "Found corresponding <li> with id '$hrefId'.\n";

            // Get the text content of the <li> tag, excluding the nested <a> tag
            $liText = '';
            foreach ($liTag->childNodes as $child) {
                if ($child->nodeName !== 'a') {
                    $liText .= $dom->saveHTML($child);
                }
            }

            // Create the <footnotes> tag
            $footnotesTag = $dom->createElement('footnotes', "\u{00a0}"); // Non-breaking space
            $footnotesTag->setAttribute('data-value', $dataValue);
            $footnotesTag->setAttribute('data-text', trim($liText));

            // Replace the <a> tag with the <footnotes> tag
            $aTag->parentNode->replaceChild($footnotesTag, $aTag);

            // Remove the <li> tag
            $liTag->parentNode->removeChild($liTag);
        } else {
            echo "No corresponding <li> found for href '$href'.\n";
        }
    }
}

// Extract the inner content of the <body> tag to save clean HTML
$bodyContent = '';
foreach ($dom->getElementsByTagName('body')->item(0)->childNodes as $child) {
    $bodyContent .= $dom->saveHTML($child);
}

// Save the modified HTML to the output file
file_put_contents($outputFile, $bodyContent);
echo "Processing complete. Output saved to $outputFile.\n";

?>