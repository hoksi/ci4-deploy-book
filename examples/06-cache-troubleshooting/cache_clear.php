<?php

/**
 * cache_clear.php — php-cli가 막힌 환경에서 `php spark cache:clear`를 대신하는 1회성 스크립트.
 *
 * CodeIgniter\Boot::bootWorker()는 라우팅/컨트롤러 디스패치 없이
 * 프레임워크 서비스(헬퍼 포함)만 초기화해줍니다. FrankenPHP 워커 모드를
 * 위해 공식 제공되는 메서드지만, 이런 1회성 스크립트에도 그대로 쓸 수 있습니다.
 *
 * 사용법:
 *   1. 아래 TOKEN 값을 추측 불가능한 임의의 문자열로 바꾼다
 *   2. 문서 루트(index.php와 같은 위치)에 업로드한다
 *   3. https://your-domain.com/cache_clear.php?token=<위 값> 으로 접속해 확인한다
 *   4. 확인 즉시 서버에서 삭제한다
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

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// 실제 프로젝트 구조에 맞게 경로를 조정하세요 (index.php와 같은 상대 위치 기준)
require FCPATH . '../app/Config/Paths.php';

$paths = new Config\Paths();

require $paths->systemDirectory . '/Boot.php';   // Composer 오토로드 전에 Boot 클래스를 직접 로드
\CodeIgniter\Boot::bootWorker($paths);           // 라우팅 없이 프레임워크 서비스만 초기화

cache()->clean();

echo 'CI4 캐시 초기화 완료: ' . date('Y-m-d H:i:s');
