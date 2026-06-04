<?php
@date_default_timezone_set('Asia/Jakarta');


ob_start();

try {
    require_once __DIR__ . '/../auth/auth.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../helpers/avatar.php';
} catch (Throwable $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'notifications' => [],
        'error' => 'Failed to load required files: ' . $e->getMessage(),
        'meta' => [
            'pending_loans' => 0,
            'pending_leaves' => 0,
            'overdue_count' => 0,
            'birthday_count' => 0,
            'badge_total' => 0,
        ],
    ]);
    exit;
}


ob_end_clean();
header('Content-Type: application/json');

$userId = (int)($_SESSION['user_id'] ?? 0);
$roleStr = strtolower(trim($_SESSION['role'] ?? ''));
$isAdmin = $roleStr && preg_match('/admin|administrator/', $roleStr);

$limit = 50;
$items = [];
$pendingLoanTotal = 0;
$pendingLeaveTotal = 0;
$pendingPasswordTotal = 0;
$overdueCounter = 0;
$birthdayCount = 0;

try {
    
    $birthdayRows = [];
    try {
        $birthdayStmt = $pdo->prepare("SELECT id, username, full_name, avatar, gender, birth_date FROM users WHERE is_active = 1 AND birth_date IS NOT NULL AND DATE_FORMAT(birth_date, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d') ORDER BY full_name ASC");
        $birthdayStmt->execute();
        $birthdayRows = $birthdayStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $birthdayErr) {
        $birthdayRows = [];
    }

    if ($birthdayRows) {
        $birthdayCount = count($birthdayRows);
        $todayDate = date('Y-m-d');
        $items[] = [
            'kind' => 'birthday',
            'id' => 'birthday-' . date('Ymd'),
            'created_at' => $todayDate . ' 00:00:01',
            'thread_date' => $todayDate,
            'birthday_users' => array_map(function ($row) {
                return [
                    'id' => (int)$row['id'],
                    'full_name' => $row['full_name'] ?? ($row['username'] ?? 'Pengguna'),
                    'username' => $row['username'] ?? null,
                    'avatar_url' => getAvatarUrl($row),
                ];
            }, $birthdayRows)
        ];
    }

    
    try {
        if ($isAdmin) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tool_permits WHERE status = 'pending' AND created_at <= DATE_ADD(NOW(), INTERVAL 7 HOUR)");
            $stmt->execute();
            $pendingLoanTotal = (int)$stmt->fetchColumn();

            $sql = "SELECT tp.*, 
                           ufrom.full_name AS from_user_name, uto.full_name AS to_user_name,
                           t.name AS tool_name, t.code AS tool_code
                    FROM tool_permits tp
                    LEFT JOIN users ufrom ON ufrom.id = tp.from_user_id
                    LEFT JOIN users uto ON uto.id = tp.to_user_id
                    LEFT JOIN tools t ON t.id = tp.tool_id
                    WHERE tp.status = 'pending' AND tp.created_at <= DATE_ADD(NOW(), INTERVAL 7 HOUR)
                    ORDER BY tp.created_at DESC
                    LIMIT " . (int)$limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tool_permits WHERE status = 'pending' AND created_at <= DATE_ADD(NOW(), INTERVAL 7 HOUR) AND ((to_user_id = :uid AND permit_type IN ('handover','return')) OR (from_user_id = :uid2 AND permit_type = 'handover'))");
            $stmt->execute([':uid' => $userId, ':uid2' => $userId]);
            $pendingLoanTotal = (int)$stmt->fetchColumn();

            $sql = "SELECT tp.*, 
                           ufrom.full_name AS from_user_name, uto.full_name AS to_user_name,
                           t.name AS tool_name, t.code AS tool_code
                    FROM tool_permits tp
                    LEFT JOIN users ufrom ON ufrom.id = tp.from_user_id
                    LEFT JOIN users uto ON uto.id = tp.to_user_id
                    LEFT JOIN tools t ON t.id = tp.tool_id
                    WHERE tp.status = 'pending' AND tp.created_at <= DATE_ADD(NOW(), INTERVAL 7 HOUR) AND ((tp.to_user_id = :uid AND tp.permit_type IN ('handover','return')) OR (tp.from_user_id = :uid2 AND tp.permit_type = 'handover'))
                    ORDER BY tp.created_at DESC
                    LIMIT " . (int)$limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':uid' => $userId, ':uid2' => $userId]);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        
        $loanGroups = [];
        foreach ($rows as $r) {
            $groupKey = ($r['from_user_id'] ?? 0) . '_' . ($r['to_user_id'] ?? 0) . '_' . ($r['permit_type'] ?? '');
            if (!isset($loanGroups[$groupKey])) {
                $loanGroups[$groupKey] = [
                    'kind' => 'loan_group',
                    'ids' => [],
                    'created_at' => $r['created_at'],
                    'permit_type' => $r['permit_type'],
                    'from_user_name' => $r['from_user_name'] ?? null,
                    'to_user_name' => $r['to_user_name'] ?? null,
                    'reason' => $r['reason'] ?? null,
                    'location' => $r['location'] ?? null,
                    'tools' => [],
                ];
            }
            $loanGroups[$groupKey]['ids'][] = (int)$r['id'];
            $loanGroups[$groupKey]['tools'][] = [
                'id' => (int)$r['id'],
                'tool_name' => $r['tool_name'] ?? null,
                'tool_code' => $r['tool_code'] ?? null,
                'photo_proof_path' => $r['photo_proof_path'] ?? null,
                'start_date' => $r['start_date'] ?? null,
                'end_date' => $r['end_date'] ?? null,
                'location' => $r['location'] ?? null,
            ];
            
            if ($r['created_at'] < $loanGroups[$groupKey]['created_at']) {
                $loanGroups[$groupKey]['created_at'] = $r['created_at'];
            }
        }
        foreach ($loanGroups as $group) {
            $items[] = $group;
        }
    } catch (Throwable $e) {
        
    }

    
    try {
        
        if (date('H:i') >= '17:30') {
            throw new Exception('Cutoff passed');
        }
        $today = date('Y-m-d');
        $sql = "SELECT s.*, u.full_name AS admin_name
                FROM schedules s
                LEFT JOIN users u ON u.id = s.created_by
                INNER JOIN schedule_assignees sa ON sa.schedule_id = s.id
                WHERE sa.user_id = :uid AND s.schedule_date = :today
                ORDER BY s.created_at DESC
                LIMIT $limit";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid' => $userId, ':today' => $today]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $items[] = [
                'kind' => 'schedule',
                'id' => (int)$r['id'],
                'created_at' => $r['created_at'],
                'status' => 'info',
                'title' => 'Jadwal Hari Ini',
                'destination' => $r['destination'],
                'details' => $r['details'],
                'schedule_date' => $r['schedule_date'],
                'created_by' => $r['admin_name'] ?? 'Administrator',
            ];
        }
    } catch (Throwable $e) {
        
    }

    
    try {
                $overdueSql = "
                        SELECT tp.id, tp.tool_id, tp.to_user_id, tp.start_date, tp.approved_at, tp.created_at,
                                     t.name AS tool_name, t.code AS tool_code,
                                     uto.full_name AS borrower_name,
                                     tp.end_date,
                                     TIMESTAMPDIFF(HOUR, COALESCE(tp.start_date, tp.approved_at, tp.created_at), NOW()) AS hours_out
                        FROM tool_permits tp
                        JOIN tools t ON t.id = tp.tool_id
                        JOIN users uto ON uto.id = tp.to_user_id
            WHERE tp.status = 'approved'
              AND tp.permit_type IN ('loan', 'handover', 'project')
              AND t.tool_type = 'company'
              AND t.current_status IN ('Loan', 'Handover', 'Project')
              AND (
                  (tp.end_date IS NOT NULL AND tp.end_date < NOW())
                  OR (tp.end_date IS NULL AND TIMESTAMPDIFF(HOUR, COALESCE(tp.start_date, tp.approved_at, tp.created_at), NOW()) > 72)
              )
                            AND uto.role = 'technician'
                            AND uto.is_active = 1
              AND NOT EXISTS (
                  SELECT 1 FROM tool_permits ret
                  WHERE ret.tool_id = tp.tool_id AND ret.status = 'approved'
                  AND ret.permit_type IN ('return', 'force_return') AND ret.id > tp.id
              )
              AND tp.id = (
                  SELECT tp2.id
                  FROM tool_permits tp2
                  WHERE tp2.tool_id = tp.tool_id
                    AND tp2.status = 'approved'
                    AND tp2.permit_type IN ('loan', 'handover', 'project')
                  ORDER BY COALESCE(tp2.approved_at, tp2.created_at) DESC, tp2.id DESC
                  LIMIT 1
              )
        ";

        if (!$isAdmin) {
            $overdueSql .= " AND tp.to_user_id = :uid";
        }

        $stmt = $pdo->prepare($overdueSql);
        if ($isAdmin) {
            $stmt->execute();
        } else {
            $stmt->execute([':uid' => $userId]);
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $overdueCounter = count($rows);

        foreach ($rows as $r) {
            $startRaw = $r['start_date'] ?: $r['approved_at'] ?: $r['created_at'];
            $borrowedAt = $startRaw ? date('Y-m-d H:i:s', strtotime($startRaw)) : null;
            $dueAt = !empty($r['end_date']) ? date('Y-m-d H:i:s', strtotime($r['end_date'])) : ($borrowedAt ? date('Y-m-d H:i:s', strtotime($borrowedAt . ' +3 days')) : null);
            $hoursOut = (int)($r['hours_out'] ?? 0);
            if (!empty($r['end_date']) && strtotime($r['end_date']) < time()) {
                $extraHours = max((int)round((time() - strtotime($r['end_date'])) / 3600), 0);
            } else {
                $extraHours = max($hoursOut - 72, 0);
            }
            $overText = 'Telah melewati batas pinjam 3 hari';
            if ($extraHours > 0) {
                $days = intdiv($extraHours, 24);
                $hours = $extraHours % 24;
                $segments = [];
                if ($days > 0) {
                    $segments[] = $days . ' hari';
                }
                if ($hours > 0) {
                    $segments[] = $hours . ' jam';
                }
                if ($segments) {
                    $overText = 'Terlambat ' . implode(' ', $segments);
                }
            }

            $items[] = [
                'kind' => 'overdue',
                'id' => (int)$r['id'],
                'created_at' => $r['created_at'],
                'tool_name' => $r['tool_name'] ?? null,
                'tool_code' => $r['tool_code'] ?? null,
                'borrower_name' => $r['borrower_name'] ?? ($isAdmin ? 'Pengguna' : null),
                'borrowed_at' => $borrowedAt,
                'due_at' => $dueAt,
                'hours_out' => $hoursOut,
                'over_text' => $overText,
            ];
        }
    } catch (Throwable $overdueErr) {
        
    }

    
    if ($isAdmin) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
            $stmt->execute();
            $pendingLeaveTotal = (int)$stmt->fetchColumn();

            $sql = "SELECT lr.*, u.full_name AS user_name, u.username
                    FROM leave_requests lr
                    LEFT JOIN users u ON u.id = lr.user_id
                    WHERE lr.status = 'pending'
                    ORDER BY lr.created_at DESC
                    LIMIT " . (int)$limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $items[] = [
                    'kind' => 'leave',
                    'id' => (int)$r['id'],
                    'created_at' => $r['created_at'],
                    'status' => $r['status'],
                    'type' => $r['type'],
                    'user_name' => $r['user_name'] ?? $r['username'] ?? 'User',
                    'start_date' => $r['start_date'] ?? null,
                    'end_date' => $r['end_date'] ?? null,
                    'proof_path' => $r['proof_path'] ?? null,
                    'reason' => $r['reason'] ?? null,
                ];
            }
        } catch (Throwable $e) {
            
        }
    }

    
    $pendingAttendanceRequestTotal = 0;
    if ($isAdmin) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance_requests WHERE status = 'pending'");
            $stmt->execute();
            $pendingAttendanceRequestTotal = (int)$stmt->fetchColumn();

            $sql = "SELECT ar.*, u.full_name AS user_name, u.username
                    FROM attendance_requests ar
                    LEFT JOIN users u ON u.id = ar.user_id
                    WHERE ar.status = 'pending'
                    ORDER BY ar.created_at DESC
                    LIMIT " . (int)$limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $items[] = [
                    'kind' => 'attendance_request',
                    'id' => (int)$r['id'],
                    'created_at' => $r['created_at'],
                    'status' => $r['status'],
                    'user_name' => $r['user_name'] ?? $r['username'] ?? 'User',
                    'attendance_date' => $r['attendance_date'] ?? null,
                    'reason' => $r['reason'] ?? null,
                    'today_plan' => $r['today_plan'] ?? null,
                    'location_name' => $r['location_name'] ?? null,
                    'request_type' => $r['request_type'] ?? 'checkin',
                    'missed_checkout_date' => $r['missed_checkout_date'] ?? null,
                    'requested_check_in_time' => $r['requested_check_in_time'] ?? null,
                    'requested_check_out_time' => $r['requested_check_out_time'] ?? null,
                    'proof_path' => $r['photo_path'] ?? null,
                ];
            }
        } catch (Throwable $e) {
            
        }
    }
    
    $pendingCutiTotal = 0;
    try {
        $cutiWhere = '';
        $cutiParams = [];
        $isManager = ($roleStr === 'technician_manager');
        $isDirektur = ($roleStr === 'direktur');

        if ($isManager) {
            $cutiWhere = "cr.status = 'pending'";
        } elseif ($isAdmin) {
            $cutiWhere = "cr.status IN ('pending','manager_approved')";
        } elseif ($isDirektur) {
            $cutiWhere = "cr.status IN ('pending','manager_approved','admin_approved')";
        }

        if ($cutiWhere) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cuti_requests cr WHERE $cutiWhere");
            $stmt->execute();
            $pendingCutiTotal = (int)$stmt->fetchColumn();

            $sql = "SELECT cr.*, u.full_name AS user_name, u.username
                    FROM cuti_requests cr
                    LEFT JOIN users u ON u.id = cr.user_id
                    WHERE $cutiWhere
                    ORDER BY cr.created_at DESC
                    LIMIT " . (int)$limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $items[] = [
                    'kind' => 'cuti',
                    'id' => (int)$r['id'],
                    'created_at' => $r['created_at'],
                    'status' => $r['status'],
                    'user_name' => $r['user_name'] ?? $r['username'] ?? 'Staff',
                    'start_date' => $r['start_date'],
                    'end_date' => $r['end_date'],
                    'total_days' => (int)$r['total_days'],
                    'reason' => $r['reason'] ?? null,
                ];
            }
        }
    } catch (Throwable $e) {
        
    }

    
    if ($isAdmin) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_reset_requests WHERE status = 'pending'");
            $stmt->execute();
            $pendingPasswordTotal = (int)$stmt->fetchColumn();

            $sql = "SELECT prr.*, u.full_name, u.username, u.email
                    FROM password_reset_requests prr
                    JOIN users u ON u.id = prr.user_id
                    WHERE prr.status = 'pending'
                    ORDER BY prr.created_at DESC
                    LIMIT $limit";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $r) {
                $items[] = [
                    'kind' => 'password_reset',
                    'id' => (int)$r['id'],
                    'created_at' => $r['created_at'],
                    'status' => $r['status'],
                    'full_name' => $r['full_name'] ?? $r['username'] ?? 'User',
                    'username' => $r['username'] ?? null,
                    'email' => $r['email'] ?? null,
                ];
            }
        } catch (Throwable $passwordErr) {
            $pendingPasswordTotal = 0;
        }
    }

    
    if (!$isAdmin) {
        try {
            $stmt = $pdo->prepare("SELECT mr.*, u.full_name AS requester_name
                FROM material_requests mr
                LEFT JOIN users u ON u.id = mr.user_id
                WHERE mr.user_id = ? AND mr.sales_edited_at IS NOT NULL AND mr.sales_edit_read = 0
                ORDER BY mr.sales_edited_at DESC
                LIMIT " . (int)$limit);
            $stmt->execute([$userId]);
            $editedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($editedRows as $r) {
                $items[] = [
                    'kind' => 'material_request_edited',
                    'id' => (int)$r['id'],
                    'created_at' => $r['sales_edited_at'],
                    'status' => $r['status'],
                    'edit_note' => $r['sales_edit_note'] ?? '',
                    'project_id' => (int)$r['project_id'],
                ];
            }
        } catch (Throwable $e) {
            
        }
    }

    
    $pendingMaterialTotal = 0;
    if ($isAdmin) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM material_requests WHERE status = 'sales_approved'");
            $stmt->execute();
            $pendingMaterialTotal = (int)$stmt->fetchColumn();

            $sql = "SELECT mr.*, u.full_name AS requester_name
                    FROM material_requests mr
                    LEFT JOIN users u ON u.id = mr.user_id
                    WHERE mr.status = 'sales_approved'
                    ORDER BY mr.created_at DESC
                    LIMIT " . (int)$limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $r) {
                $items[] = [
                    'kind' => 'material_request',
                    'id' => (int)$r['id'],
                    'created_at' => $r['created_at'],
                    'status' => $r['status'],
                    'requester_name' => $r['requester_name'] ?? 'Technician',
                    'project_id' => (int)$r['project_id'],
                ];
            }
        } catch (Throwable $materialErr) {
            $pendingMaterialTotal = 0;
        }
    }

    
    $pendingDeliveryTotal = 0;
    $isDriver = ($roleStr === 'driver');
    if ($isDriver) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM material_requests WHERE status = 'admin_approved' AND (driver_pickup_by = :uid OR driver_pickup_by IS NULL)");
            $stmt->execute([':uid' => $userId]);
            $pendingDeliveryTotal = (int)$stmt->fetchColumn();

            $sql = "SELECT mr.*, u.full_name AS requester_name
                    FROM material_requests mr
                    LEFT JOIN users u ON u.id = mr.user_id
                    WHERE mr.status = 'admin_approved'
                    AND (mr.driver_pickup_by = :uid OR mr.driver_pickup_by IS NULL)
                    ORDER BY mr.updated_at DESC
                    LIMIT " . (int)$limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':uid' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $r) {
                $items[] = [
                    'kind' => 'material_delivery',
                    'id' => (int)$r['id'],
                    'created_at' => $r['updated_at'] ?? $r['created_at'],
                    'status' => $r['status'],
                    'requester_name' => $r['requester_name'] ?? 'Technician',
                    'project_id' => (int)$r['project_id'],
                ];
            }
        } catch (Throwable $deliveryErr) {
            $pendingDeliveryTotal = 0;
        }
    }

    
    usort($items, function($a, $b){
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });
    $items = array_slice($items, 0, $limit);

    $meta = [
        'pending_loans' => $pendingLoanTotal,
        'pending_leaves' => $pendingLeaveTotal,
        'pending_cuti' => $pendingCutiTotal,
        'overdue_count' => $overdueCounter,
        'pending_password_resets' => $pendingPasswordTotal,
        'pending_material_requests' => $pendingMaterialTotal,
        'pending_deliveries' => $pendingDeliveryTotal,
        'pending_attendance_requests' => $pendingAttendanceRequestTotal,
        'birthday_count' => $birthdayCount,
        'badge_total' => $pendingLoanTotal + $pendingLeaveTotal + $pendingCutiTotal + $pendingPasswordTotal + $pendingMaterialTotal + $pendingDeliveryTotal + $pendingAttendanceRequestTotal + $overdueCounter + ($birthdayCount > 0 ? 1 : 0),
    ];

    echo json_encode(['notifications' => $items, 'meta' => $meta]);
} catch (Exception $e) {
    echo json_encode([
        'notifications' => [],
        'error' => $e->getMessage(),
        'meta' => [
            'pending_loans' => 0,
            'pending_leaves' => 0,
            'overdue_count' => 0,
            'birthday_count' => 0,
            'badge_total' => 0,
        ],
    ]);
}
?>
