<?php

/**
 * opcache_reset.php — php-cli가 막힌 환경에서 OPcache를 초기화하는 1회성 스크립트.
 *
 * 사용법:
 *   1. 아래 TOKEN 값을 추측 불가능한 임의의 문자열로 바꾼다
 *      (예: php -r "echo bin2hex(random_bytes(24));" 로 생성)
 *   2. 문서 루트(index.php와 같은 위치)에 업로드한다
 *   3. https://your-domain.com/opcache_reset.php?token=<위 값> 으로 접속해 확인한다
 *   4. 확인 즉시 서버에서 삭제한다 — 계속 띄워두면 누구나 토큰을 알아낼 때까지
 *      무한정 재시도할 수 있는 공격 표면이 됩니다.
 */

$TOKEN = 'CHANGE_ME_TO_A_RANDOM_VALUE';

if ($TOKEN === 'CHANGE_ME_TO_A_RANDOM_VALUE') {
    http_response_code(500);
    exit('TOKEN 값을 먼저 변경하세요.');
}

if (! hash_equals($TOKEN, $_GET['token'] ?? '')) {
    http_response_code(403);
    exit('Forbidden');
}

if (! function_exists('opcache_reset')) {
    echo 'OPcache 확장이 설치되어 있지 않거나 비활성화되어 있습니다.';
    exit;
}

$ok = opcache_reset();

echo $ok
    ? 'OPcache reset 완료: ' . date('Y-m-d H:i:s')
    : 'OPcache reset 실패 (opcache.restrict_api 설정 등으로 막혀 있을 수 있습니다)';
