# 5장. DB 마이그레이션을 CLI 없이 처리하기

4장에서 코드는 `git pull`로 배포가 끝났습니다. 문제는 **스키마가 바뀐 마이그레이션**입니다 — `php spark migrate`를 서버에서 실행할 수 없다면, DB가 쓰는 엔진(SQLite냐 MySQL이냐)에 따라 전략이 완전히 달라집니다.

## SQLite: 로컬로 가져와서 마이그레이트하고 재업로드

SQLite는 그냥 파일 하나라서, **그 파일을 잠깐 로컬로 가져와 php-cli가 되는 환경에서 마이그레이션을 수행한 뒤 다시 올리는** 방법이 유일하면서도 가장 간단합니다.

```python
# migrate_prod_db.py — 1권 13장과 동일한 패턴
import datetime, shutil, subprocess, getpass
from pathlib import Path
import paramiko

HOST, USER = "your-host.com", "your_id"
REMOTE_DB  = "writable/database.db"
LOCAL_REPO = Path("/path/to/your/local/checkout")
LOCAL_DB   = LOCAL_REPO / "writable" / "database.db"

password = getpass.getpass(f"{USER}@{HOST} 비밀번호: ")
ts = datetime.datetime.now().strftime("%Y%m%d%H%M%S")

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=password)

client.exec_command(f"cp {REMOTE_DB} {REMOTE_DB}.bak.{ts}")   # 1) 원격 백업

sftp = client.open_sftp()
downloaded = Path(f"/tmp/prod_db.{ts}.sqlite")
sftp.get(REMOTE_DB, str(downloaded))                          # 2) 다운로드

dev_backup = Path(f"/tmp/dev_db.{ts}.sqlite")
shutil.copy2(LOCAL_DB, dev_backup)                             # 3) 로컬 dev DB 임시 백업
shutil.copy2(downloaded, LOCAL_DB)
subprocess.run(["php", "spark", "migrate", "--all"], cwd=LOCAL_REPO, check=True)
migrated = Path(f"/tmp/migrated_db.{ts}.sqlite")
shutil.copy2(LOCAL_DB, migrated)
shutil.copy2(dev_backup, LOCAL_DB)                             # 로컬 dev DB 원상복구

sftp.put(str(migrated), REMOTE_DB)                             # 4) 업로드
sftp.close()
client.close()
```

이 패턴의 핵심은 **서버 원본 백업(1단계)을 가장 먼저 하는 것**입니다. 중간에 뭐가 잘못되더라도 `.bak.<타임스탬프>` 파일로 되돌릴 수 있습니다.

## MySQL: 원격 접속이 허용되는가가 갈림길

MySQL은 파일 하나로 복사해서 옮길 수 없으니, SQLite와는 다른 접근이 필요합니다. 먼저 확인할 것 — **호스팅사가 "원격 MySQL 접속"을 허용하는가**입니다 (cPanel 계열이면 보통 "Remote MySQL"이라는 메뉴로 특정 IP를 허용 목록에 추가할 수 있습니다).

### 원격 접속이 허용되는 경우 (가장 쉬움)

로컬 `.env`의 DB 접속 정보를 운영 DB 호스트로 잠깐 바꿔서, 로컬에서 바로 마이그레이션을 실행합니다.

```ini
# .env (임시로 운영 DB를 가리키도록 변경 — 작업 후 반드시 되돌릴 것)
database.default.hostname = your-db-host.com
database.default.database = prod_db_name
database.default.username = prod_db_user
database.default.password = prod_db_password
database.default.port     = 3306
```

```bash
php spark migrate --all
```

끝나면 **반드시 `.env`를 로컬 DB 설정으로 되돌리세요** — 이 상태로 그냥 작업을 계속하면 로컬 개발 중에 실수로 운영 DB에 테스트 데이터를 넣는 사고로 이어집니다. 작업 직전에 운영 DB를 백업해두는 것도 잊지 마세요(`mysqldump`는 뒤에서 다룹니다).

### 원격 접속이 안 되는 경우: phpMyAdmin으로 수동 SQL

원격 접속이 막혀 있다면 phpMyAdmin 같은 웹 관리 도구로 직접 SQL을 실행하는 수밖에 없습니다. CI4 마이그레이션 파일의 `addField()` 호출을 그대로 SQL로 옮기면 됩니다.

```php
// 마이그레이션 파일
$this->forge->addField([
    'id'      => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
    'title'   => ['type' => 'VARCHAR', 'constraint' => 200],
    'content' => ['type' => 'TEXT'],
]);
$this->forge->addPrimaryKey('id');
$this->forge->createTable('posts', true);
```

```sql
-- phpMyAdmin SQL 탭에 그대로 실행
CREATE TABLE IF NOT EXISTS `posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `content` TEXT NOT NULL,
  PRIMARY KEY (`id`)
);
```

이 방식의 단점은 명확합니다 — **마이그레이션 변경이 생길 때마다 수동으로 SQL을 다시 옮겨 적어야 하고, 실수할 여지가 큽니다.** 테이블 몇 개짜리 작은 프로젝트에서나 버틸 만한 방법이고, 스키마 변경이 잦다면 원격 접속을 뚫는 쪽(호스팅사 문의, 또는 7장 이후의 VPS/클라우드 전환)을 더 진지하게 고려해야 합니다.

## 두 방식 모두에서 지켜야 할 원칙

- **먼저 백업한다.** SQLite든 MySQL이든, 스키마를 바꾸기 전에 항상 백업부터입니다.
- **로컬 개발 환경을 운영과 혼동하지 않는다.** SQLite 방식은 스크립트가 자동으로 로컬 dev DB를 복원해주지만, MySQL 원격 접속 방식은 `.env`를 손으로 되돌려야 한다는 걸 기억하세요.
- **마이그레이션은 되돌릴 수 있게 작성한다.** CI4 마이그레이션의 `down()` 메서드를 성의 없이 비워두지 마세요. 운영에서 문제가 생겼을 때 롤백할 유일한 수단입니다.

## 체크리스트

- [ ] 운영 DB 백업을 먼저 만들었는가
- [ ] (MySQL) 원격 접속 허용 여부를 호스팅사 패널에서 확인했는가
- [ ] (MySQL 원격 접속 사용 시) 작업 후 `.env`를 로컬 설정으로 되돌렸는가
- [ ] 마이그레이션 파일에 `down()`이 제대로 구현되어 있는가

다음 장에서는 "코드를 분명히 고쳤는데 반영이 안 된다"는, 배포 후 가장 흔하게 마주치는 증상을 진단하는 법을 다룹니다.
