<?php

// Developed by Host Media Ltd
// https://hostmedia.uk
// Version 1.0.0

use WHMCS\Carbon;
use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$reportdata["title"] = "Income by Domain";
$reportdata["description"] = "This report shows domain extensions with the number of active domains and total charges, with a breakdown by registration period.";

// Set up table headings
$reportdata["tableheadings"] = array(
    "Domain Extension",
    "Registration Period",
    "Number of Active Domains",
    "Total Charges",
    "Average Charge per Domain"
);

// Get all domain extensions from tbldomainpricing
$domainExtensions = Capsule::table('tbldomainpricing')
    ->select('extension')
    ->distinct()
    ->orderBy('extension')
    ->get();

// Initialize array to store domain extension data
$extensionData = [];
$extensionTotals = [];

// Loop through each domain extension
foreach ($domainExtensions as $extension) {
    $ext = $extension->extension;
    
    // Make sure extension starts with a dot for proper matching
    if (substr($ext, 0, 1) !== '.') {
        $ext = '.' . $ext;
    }
    
    // Count active domains with this extension
    $activeDomains = Capsule::table('tbldomains')
        ->where('domain', 'LIKE', '%' . $ext)
        ->where('status', 'Active')
        ->count();
    
    // Skip if no active domains with this extension
    if ($activeDomains == 0) {
        continue;
    }
    
    // Get domains with this extension grouped by registration period
    $domainsByPeriod = Capsule::table('tbldomains')
        ->select('registrationperiod', Capsule::raw('COUNT(*) as count'), Capsule::raw('SUM(recurringamount) as total_charges'))
        ->where('domain', 'LIKE', '%' . $ext)
        ->where('status', 'Active')
        ->groupBy('registrationperiod')
        ->orderBy('registrationperiod')
        ->get();
    
    $totalDomainsForExt = 0;
    $totalChargesForExt = 0;
    
    foreach ($domainsByPeriod as $period) {
        // Calculate average charge per domain for this period
        $averageCharge = $period->count > 0 ? $period->total_charges / $period->count : 0;
        
        // Add to extension data array
        $extensionData[] = [
            'extension' => $ext,
            'period' => $period->registrationperiod,
            'active_domains' => $period->count,
            'total_charges' => $period->total_charges,
            'average_charge' => $averageCharge
        ];
        
        $totalDomainsForExt += $period->count;
        $totalChargesForExt += $period->total_charges;
    }
    
    // Add extension total row
    $extensionTotals[] = [
        'extension' => $ext,
        'active_domains' => $totalDomainsForExt,
        'total_charges' => $totalChargesForExt,
        'average_charge' => $totalDomainsForExt > 0 ? $totalChargesForExt / $totalDomainsForExt : 0
    ];
}

// Sort by extension name
usort($extensionData, function($a, $b) {
    return strcmp($a['extension'], $b['extension']);
});

// Sort extension totals by extension name
usort($extensionTotals, function($a, $b) {
    return strcmp($a['extension'], $b['extension']);
});

// Add data to report
$currentExtension = '';
foreach ($extensionData as $data) {
    // If this is a new extension, add a separator row
    if ($currentExtension != '' && $currentExtension != $data['extension']) {
        // Add subtotal for previous extension
        foreach ($extensionTotals as $total) {
            if ($total['extension'] == $currentExtension) {
                // Create a row with HTML styling for background color
                $reportdata["tablevalues"][] = array(
                    '<div style="background-color:#f2f2f2; padding:3px;"><strong>' . $currentExtension . ' Total</strong></div>',
                    '<div style="background-color:#f2f2f2; padding:3px;"><strong>All Periods</strong></div>',
                    '<div style="background-color:#f2f2f2; padding:3px;"><strong>' . $total['active_domains'] . '</strong></div>',
                    '<div style="background-color:#f2f2f2; padding:3px;"><strong>' . formatCurrency($total['total_charges']) . '</strong></div>',
                    '<div style="background-color:#f2f2f2; padding:3px;"><strong>' . formatCurrency($total['average_charge']) . '</strong></div>'
                );
                break;
            }
        }
        $reportdata["tablevalues"][] = array('', '', '', '', '');
    }
    
    $currentExtension = $data['extension'];
    
    $reportdata["tablevalues"][] = array(
        $data['extension'],
        $data['period'] . ' Year' . ($data['period'] > 1 ? 's' : ''),
        $data['active_domains'],
        formatCurrency($data['total_charges']),
        formatCurrency($data['average_charge'])
    );
}

// Add the last extension's subtotal
foreach ($extensionTotals as $total) {
    if ($total['extension'] == $currentExtension) {
        // Create a row with HTML styling for background color
        $reportdata["tablevalues"][] = array(
            '<div style="background-color:#f2f2f2; padding:3px;"><strong>' . $currentExtension . ' Total</strong></div>',
            '<div style="background-color:#f2f2f2; padding:3px;"><strong>All Periods</strong></div>',
            '<div style="background-color:#f2f2f2; padding:3px;"><strong>' . $total['active_domains'] . '</strong></div>',
            '<div style="background-color:#f2f2f2; padding:3px;"><strong>' . formatCurrency($total['total_charges']) . '</strong></div>',
            '<div style="background-color:#f2f2f2; padding:3px;"><strong>' . formatCurrency($total['average_charge']) . '</strong></div>'
        );
        break;
    }
}

// Add grand totals row
$totalDomains = array_sum(array_column($extensionTotals, 'active_domains'));
$totalCharges = array_sum(array_column($extensionTotals, 'total_charges'));
$totalAverage = $totalDomains > 0 ? $totalCharges / $totalDomains : 0;

$reportdata["tablevalues"][] = array('', '', '', '', '');
$reportdata["tablevalues"][] = array(
    '<div style="background-color:#e6e6e6; padding:3px;"><strong>Grand Total</strong></div>',
    '<div style="background-color:#e6e6e6; padding:3px;"><strong>All Extensions</strong></div>',
    '<div style="background-color:#e6e6e6; padding:3px;"><strong>' . $totalDomains . '</strong></div>',
    '<div style="background-color:#e6e6e6; padding:3px;"><strong>' . formatCurrency($totalCharges) . '</strong></div>',
    '<div style="background-color:#e6e6e6; padding:3px;"><strong>' . formatCurrency($totalAverage) . '</strong></div>'
);
