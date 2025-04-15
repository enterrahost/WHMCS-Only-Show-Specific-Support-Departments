/*
|--------------------------------------------------------------------------
| WHMCS ShowSupportDept Hook
|--------------------------------------------------------------------------
| This is for WHMCS and has been tested on version 8.12.x
|
| This hook hides or shows / hides support deparments based on the client group
| the user is logged in and is in a specific client group (e.g., group ID = 1).
|
| In this code only client group 11 is able to see Support Depardment ID 17 (clientgroup ID 11 "Beta Testers" can see support department ID 17 "Beta Testers" etc.
| we have also extended this to enable a mix of what can be seen or not. 
| if a department is explicity hidden in WHMCS it is still hidden here,
| also if you generally visible departments, like Sales or Support they are visible to everyone
| if your users are not in any group they will only see general groups not specified here
| Lastly the below "hidden" groups are not set to hidden in your WHMCS Support Departments
|
| You're welcome to use and modify this for your needs. It can be adapted
| to show/hide support deparments as needed
| — Enterrahost
*/
<?php

use WHMCS\Database\Capsule;

add_hook('ClientAreaPageSubmitTicket', 1, function($vars) {
    try {
        // Group to Department Mapping (EXACTLY as specified)
        $groupDeptRules = [
            11 => [17],    // ClientGroup 11 Beta Testers → Dept 17 - Beta Testers
            12 => [16],    // ClientGroup 12 Affiliates → Dept 16 - Affiliates 
            14 => [16, 18],    // ClientGroup 14 Affiliate & Agency→ Dept 16 & 18 - Affiliates and Agency
            15 => [16, 17], // ClientGroup 15 Affiliate & Beta Testers→ Depts 16 & 17 - Affiliates - Beta Testers
            13 => [18]     // ClientGroup 13 Agency → Dept 18 - Agency
        ];
        
        // These are the ONLY departments we'll control
        $controlledDepts = [16, 17, 18];
        
        $clientGroupId = 0; // Default for no group/not logged in

        // Get client's group if logged in
        if (!empty($_SESSION['uid'])) {
            $client = Capsule::table('tblclients')
                ->where('id', $_SESSION['uid'])
                ->first();
            $clientGroupId = $client ? (int)$client->groupid : 0;
        }

        // Filter departments (while preserving all others)
        if (!empty($vars['departments'])) {
            return [
                'departments' => array_filter($vars['departments'], function($dept) use ($groupDeptRules, $clientGroupId, $controlledDepts) {
                    $deptId = $dept['id'];
                    
                    // Always allow non-controlled departments
                    if (!in_array($deptId, $controlledDepts)) {
                        return true;
                    }
                    
                    // Check group rules for controlled departments
                    return isset($groupDeptRules[$clientGroupId]) && 
                           in_array($deptId, $groupDeptRules[$clientGroupId]);
                })
            ];
        }

    } catch (Exception $e) {
        logActivity("Department Access Hook Error: " . $e->getMessage());
    }
});

// Security hook - prevents direct URL access to restricted depts
add_hook('ClientAreaPage', 1, function($vars) {
    if ($vars['filename'] === 'submitticket' && isset($_GET['deptid'])) {
        $requestedDept = (int)$_GET['deptid'];
        $groupDeptRules = [
            11 => [17], 12 => [16], 14 => [16], 
            15 => [16, 17], 13 => [18]
        ];
        
        if (in_array($requestedDept, [6, 7, 8])) {
            $clientGroupId = 0;
            
            if (!empty($_SESSION['uid'])) {
                $client = Capsule::table('tblclients')
                    ->where('id', $_SESSION['uid'])
                    ->first();
                $clientGroupId = $client ? (int)$client->groupid : 0;
            }

            $allowed = isset($groupDeptRules[$clientGroupId]) && 
                      in_array($requestedDept, $groupDeptRules[$clientGroupId]);
            
            if (!$allowed) {
                header("Location: submitticket.php");
                exit;
            }
        }
    }
});
