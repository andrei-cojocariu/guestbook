<?php
/**
 * SignAndListFlowTest — the tsk-003 characterization net for the sign
 * (`Guestbook::create`) and list (`Guestbook::index`) flow.
 *
 * Implements the BDD contract in
 * `.ptah/audit/features/characterization-baseline.md` 1:1 (see that file's
 * "Scenario -> intended test mapping" table for the method-name contract).
 * This is a hard prerequisite gate (tsk-003): it MUST be green before any
 * behavior-changing security fix (tsk-005 output encoding, tsk-006 CSRF)
 * lands, per `.ptah/tasks/tsk-003-characterize-signlist-flow.md`. It adds
 * ZERO product code — only this test suite and its support files
 * (`bootstrap.php`, `router.php`, `phpunit.xml`).
 *
 * Black-box, real HTTP, real MySQL — no framework internals are mocked:
 *  - `setUpBeforeClass()` spawns the project's OWN `index.php` behind a real
 *    `php -S` server (via `router.php`) and issues genuine HTTP requests
 *    against it with `file_get_contents()` + a stream context (no cURL
 *    extension is installed in the frozen image).
 *  - A raw `mysqli` connection (opened with the SAME credentials already
 *    committed in `application/config/database.php` — read at runtime via
 *    that file's own text, never duplicated as a literal here, per
 *    `rules/secrets-protocol.md`) seeds/clears rows and reads back what was
 *    actually persisted, independent of what the HTTP response claims.
 *
 * Why this must run inside the frozen PHP 5.6 CLI rather than a modern host
 * runner (DEBT-8, `.ptah/audit/legacy_debt.md`): `hooks.test`
 * (`.ptah/ptah.yaml`) runs `php vendor/bin/phpunit` INSIDE
 * `ci-guestbook:frozen`, using its own PHP 5.6 interpreter to parse this
 * very file. Every line here is therefore deliberately PHP-5.6-syntax-safe:
 * no `??`, no scalar/return type hints, no 2-arg `dirname()`, no anonymous
 * classes, `array()` literals throughout.
 *
 * FEEDBACK-LOOP FLAGS (see the worker's return report for the formal
 * requests to the test-engineer-worker; both are characterized here against
 * VERIFIED real behavior, not the BDD contract's literal wording, per this
 * task's TAC: "the net asserts current output bytes"):
 *
 * 1. `characterization-baseline.md`'s "Stored HTML is currently echoed
 *    unescaped" scenario specifies a literal "<script>alert('xss')</script>"
 *    payload. Verified against the real pipeline
 *    (`system/core/Security.php:486-489`), `xss_clean()` rewrites any
 *    "<script...>"/"</script>" occurrence to the literal text "[removed]"
 *    BEFORE `strip_tags` ever runs (`Guestbook::create()`'s validation rule
 *    is `...|xss_clean|strip_tags`), so that literal payload never reaches
 *    storage intact. `test_stored_html_currently_unescaped()` below
 *    characterizes the SAME underlying defect (SEC-1, no output encoding)
 *    with a payload that genuinely survives the validation pipeline
 *    unmolested: bare HTML metacharacters (`&`, `"`, `>`) that form neither
 *    a recognized tag nor the words "script"/"xss".
 *
 * 2. `characterization-baseline.md`'s "A failed insert still reports
 *    success" scenario says "Given persistence is induced to fail". This was
 *    first attempted here as a live, black-box HTTP submission containing a
 *    4-byte UTF-8 character (over a `char_set = 'utf8'`, 3-byte-max,
 *    connection) — empirically VERIFIED (live, against `ci-guestbook:frozen`
 *    + `mysql:5.7.44`) to be silently TRUNCATED at the offending byte with
 *    only a warning, not rejected: the insert still "succeeds" (with data
 *    loss), so `$this->db->insert()` returns TRUE and never exercises the
 *    branch this bug is about. No black-box HTTP payload reliably forces
 *    `$this->db->insert()` to return FALSE against this schema (no unique
 *    constraints beyond the surrogate key; oversized/invalid values
 *    truncate rather than error under this container's effective
 *    `sql_mode`). `test_failed_insert_reports_success_bug()` below instead
 *    exercises `Guestbook_messages::set_message()` directly, in isolation,
 *    against a minimal stub standing in for CI's `$this->db` that returns
 *    FALSE from `insert()` — this is a genuine, deterministic reproduction
 *    of the exact defect (the model ignores the return value), it is just
 *    not a full HTTP round-trip for this one case. It still requires zero
 *    product-code changes and zero framework internals mocked beyond the
 *    one collaborator (`$this->db`) the bug is actually about.
 */

class SignAndListFlowTest extends PHPUnit_Framework_TestCase
{
    /** @var resource|null */
    private static $serverProcess;

    /** @var array */
    private static $serverPipes = array();

    private static $host = '127.0.0.1';
    private static $port = 8391;
    private static $baseUrl;

    /** @var mysqli */
    private static $db;

    public static function setUpBeforeClass()
    {
        self::$baseUrl = 'http://' . self::$host . ':' . self::$port;
        self::$db = self::connectDb();
        self::startServer();
    }

    public static function tearDownAfterClass()
    {
        self::stopServer();

        if (self::$db instanceof mysqli) {
            self::$db->close();
            self::$db = null;
        }
    }

    protected function setUp()
    {
        $this->clearMessages();
    }

    // ------------------------------------------------------------------
    // Scenario: A valid submission is stored and acknowledged
    // (message-submission.md, characterization-baseline.md)
    // ------------------------------------------------------------------
    public function test_valid_submission_stored_and_acknowledged()
    {
        $response = $this->request('POST', '/Guestbook/create', array(
            'name'    => 'Ada Lovelace',
            'email'   => 'ada@example.com',
            'message' => 'Characterizing the sign flow before it changes.',
        ));

        $this->assertSame(200, $response['status']);
        $this->assertContains('Your message has been processed', $response['body']);
        $this->assertSame(1, $this->countMessages());

        $rows = $this->fetchMessages();
        $this->assertSame('Ada Lovelace', $rows[0]['name']);
        $this->assertSame('ada@example.com', $rows[0]['email']);
        $this->assertSame('Characterizing the sign flow before it changes.', $rows[0]['message']);

        // The freshly-inserted message must also appear in the same
        // response's rendered timeline (create() re-reads get_messages()).
        $this->assertContains('Ada Lovelace', $response['body']);
    }

    // ------------------------------------------------------------------
    // Scenario: A tokenless POST is currently accepted (#csrf-disabled, SEC-4)
    // ------------------------------------------------------------------
    public function test_tokenless_post_currently_accepted()
    {
        // csrf_protection is FALSE (application/config/config.php) and this
        // POST deliberately includes no CSRF token field at all.
        $response = $this->request('POST', '/Guestbook/create', array(
            'name'    => 'Grace Hopper',
            'email'   => 'grace@example.com',
            'message' => 'No CSRF token accompanies this submission.',
        ));

        $this->assertSame(200, $response['status']);
        $this->assertContains('Your message has been processed', $response['body']);
        $this->assertSame(1, $this->countMessages(), '#csrf-disabled: tokenless POST is currently accepted and stored');
    }

    // ------------------------------------------------------------------
    // Scenario: Stored HTML is currently echoed unescaped (#stored-xss, SEC-1)
    // See the FEEDBACK-LOOP note in this file's header docblock.
    // ------------------------------------------------------------------
    public function test_stored_html_currently_unescaped()
    {
        $payload = 'Tom & Jerry said "hello" > everyone';

        $submit = $this->request('POST', '/Guestbook/create', array(
            'name'    => 'X & Y',
            'email'   => 'xy@example.com',
            'message' => $payload,
        ));
        $this->assertSame(200, $submit['status']);

        $rows = $this->fetchMessages();
        $this->assertSame(
            $payload,
            $rows[0]['message'],
            'payload must survive xss_clean|strip_tags unmolested for this to characterize SEC-1'
        );

        $homepage = $this->request('GET', '/');
        $this->assertContains(
            $payload,
            $homepage['body'],
            '#stored-xss: raw HTML metacharacters are echoed with no output encoding'
        );
        $this->assertNotContains(
            htmlspecialchars($payload, ENT_QUOTES, 'UTF-8'),
            $homepage['body'],
            '#stored-xss: the HTML-escaped form must NOT be what is rendered'
        );
    }

    // ------------------------------------------------------------------
    // Scenario: Timeline timestamp is the render time, not received_on
    // (#timeline-time-bug, BUG-1)
    // ------------------------------------------------------------------
    public function test_timeline_shows_render_time_bug()
    {
        $past = date('Y-m-d H:i:s', strtotime('2019-03-14 09:26:00'));
        $this->seedMessage('Time Traveler', 'time@example.com', 'Stamped in the past on purpose.', $past);

        $before = time();
        $response = $this->request('GET', '/');
        $after = time();

        $this->assertSame(200, $response['status']);

        $expectedPastDate = date('d-m-y', strtotime($past));
        $this->assertNotContains(
            $expectedPastDate,
            $response['body'],
            '#timeline-time-bug: the true received_on date must NOT appear'
        );

        $renderedNow = false;
        for ($t = $before; $t <= $after; $t++) {
            if (strpos($response['body'], date('d-m-y', $t)) !== false) {
                $renderedNow = true;
                break;
            }
        }
        $this->assertTrue(
            $renderedNow,
            '#timeline-time-bug: rendered date must be the render-time date, not received_on'
        );
    }

    // ------------------------------------------------------------------
    // Scenario: A failed insert still reports success
    // (#silent-insert-success, BUG-2)
    // ------------------------------------------------------------------
    public function test_failed_insert_reports_success_bug()
    {
        // See the FEEDBACK-LOOP FLAG #2 in this file's header docblock for
        // why this is a direct, isolated exercise of the model rather than
        // an HTTP round-trip: no black-box HTTP payload against this schema
        // reliably makes $this->db->insert() return FALSE (oversized/
        // malformed values truncate rather than error under this
        // container's effective sql_mode) -- verified live before writing
        // this version of the test.
        //
        // CI_Model::__get() only fires for an UNDEFINED property, so setting
        // real dynamic properties named `db`/`input` on the instance is used
        // directly by set_message() instead of falling through to CI's
        // get_instance() magic -- no CI bootstrap, no HTTP, no real
        // database touched by this one test.
        require_once PTAH_CHARACTERIZATION_ROOT . '/application/tests/characterization/support/ModelHarness.php';

        if (!defined('BASEPATH')) {
            define('BASEPATH', true);
        }
        require_once PTAH_CHARACTERIZATION_ROOT . '/system/core/Model.php';
        require_once PTAH_CHARACTERIZATION_ROOT . '/application/models/Guestbook_messages.php';

        $failingDb = new Ptah_FailingDbStub();
        $model = new Guestbook_messages();
        $model->db = $failingDb;
        $model->input = new Ptah_PostInputStub(array(
            'name'    => 'Cassandra',
            'email'   => 'cassandra@example.com',
            'message' => 'This insert is stubbed to fail at the db layer.',
        ));

        $result = $model->set_message();

        $this->assertTrue($failingDb->insertWasCalled, 'the stub db->insert() must actually have been invoked');
        $this->assertTrue(
            $result,
            '#silent-insert-success: set_message() must return true even though the insert failed'
        );
    }

    // ------------------------------------------------------------------
    // Scenario: Missing/too-short fields and invalid email are rejected
    // (message-submission.md)
    // ------------------------------------------------------------------
    public function test_validation_rejects_bad_input()
    {
        $cases = array(
            'short name' => array(
                'post'        => array('name' => 'Al', 'email' => 'valid@example.com', 'message' => 'Long enough message.'),
                'errorNeedle' => 'must be at least 3 characters',
            ),
            'invalid email' => array(
                'post'        => array('name' => 'Valid Name', 'email' => 'not-an-email', 'message' => 'Long enough message.'),
                'errorNeedle' => 'must contain a valid email address',
            ),
            'short message' => array(
                'post'        => array('name' => 'Valid Name', 'email' => 'valid@example.com', 'message' => 'Hi'),
                'errorNeedle' => 'must be at least 5 characters',
            ),
        );

        foreach ($cases as $label => $case) {
            $this->clearMessages();

            $response = $this->request('POST', '/Guestbook/create', $case['post']);

            $this->assertSame(200, $response['status'], $label);
            $this->assertSame(0, $this->countMessages(), $label . ': no row must be inserted on validation failure');
            $this->assertContains($case['errorNeedle'], $response['body'], $label . ': an inline validation error must be shown');
            $this->assertContains('help-block has-error', $response['body'], $label . ': the error must use the controller\'s error delimiters');
        }
    }

    // ------------------------------------------------------------------
    // Scenario: Empty timeline is hidden (timeline-rendering.md)
    // ------------------------------------------------------------------
    public function test_empty_timeline_hidden()
    {
        $response = $this->request('GET', '/');

        $this->assertSame(200, $response['status']);
        $this->assertSame(0, $this->countMessages());
        $this->assertNotContains('Previous Messages', $response['body']);
    }

    // ------------------------------------------------------------------
    // Scenario: Messages are shown newest-first (timeline-rendering.md)
    // ------------------------------------------------------------------
    public function test_messages_listed_newest_first()
    {
        $this->seedMessage('Oldest', 'oldest@example.com', 'First entry chronologically.', date('Y-m-d H:i:s', strtotime('2020-01-01 08:00:00')));
        $this->seedMessage('Middle', 'middle@example.com', 'Second entry chronologically.', date('Y-m-d H:i:s', strtotime('2020-06-01 08:00:00')));
        $this->seedMessage('Newest', 'newest@example.com', 'Third entry chronologically.', date('Y-m-d H:i:s', strtotime('2021-01-01 08:00:00')));

        $response = $this->request('GET', '/');

        $this->assertSame(200, $response['status']);
        $this->assertContains('Previous Messages', $response['body']);

        $posNewest = strpos($response['body'], 'Newest');
        $posMiddle = strpos($response['body'], 'Middle');
        $posOldest = strpos($response['body'], 'Oldest');

        $this->assertNotFalse($posNewest, 'Newest must be present');
        $this->assertNotFalse($posMiddle, 'Middle must be present');
        $this->assertNotFalse($posOldest, 'Oldest must be present');
        $this->assertLessThan($posMiddle, $posNewest, 'Newest must render before Middle');
        $this->assertLessThan($posOldest, $posMiddle, 'Middle must render before Oldest');
    }

    // ==================================================================
    // Infrastructure: real HTTP server (php -S) + real mysqli connection.
    // No product code is touched or executed beyond genuine HTTP/DB I/O.
    // ==================================================================

    private static function startServer()
    {
        $router = PTAH_CHARACTERIZATION_ROOT . '/application/tests/characterization/router.php';
        $logFile = sys_get_temp_dir() . '/ptah-characterization-server.log';

        $command = escapeshellarg(PHP_BINARY)
            . ' -S ' . self::$host . ':' . self::$port
            . ' -t ' . escapeshellarg(PTAH_CHARACTERIZATION_ROOT)
            . ' ' . escapeshellarg($router);

        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('file', $logFile, 'a'),
            2 => array('file', $logFile, 'a'),
        );

        self::$serverProcess = proc_open($command, $descriptors, self::$serverPipes, PTAH_CHARACTERIZATION_ROOT);

        if (!is_resource(self::$serverProcess)) {
            throw new RuntimeException('characterization net: failed to start the PHP built-in server');
        }

        if (isset(self::$serverPipes[0]) && is_resource(self::$serverPipes[0])) {
            fclose(self::$serverPipes[0]);
        }

        $deadline = microtime(true) + 10;
        $up = false;
        while (microtime(true) < $deadline) {
            $status = proc_get_status(self::$serverProcess);
            if (!$status['running']) {
                break;
            }

            $connection = @fsockopen(self::$host, self::$port, $errno, $errstr, 0.5);
            if (is_resource($connection)) {
                fclose($connection);
                $up = true;
                break;
            }

            usleep(100000);
        }

        if (!$up) {
            $log = is_readable($logFile) ? file_get_contents($logFile) : '(no log)';
            self::stopServer();
            throw new RuntimeException('characterization net: PHP built-in server never became ready. Log: ' . $log);
        }
    }

    private static function stopServer()
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
            self::$serverProcess = null;
        }
    }

    /**
     * @param string $method
     * @param string $path
     * @param array  $post
     * @param array  $extraHeaders
     * @return array{status:int,body:string}
     */
    private function request($method, $path, array $post = array(), array $extraHeaders = array())
    {
        $url = self::$baseUrl . $path;

        $headers = $extraHeaders;
        $content = '';
        if (strtoupper($method) === 'POST') {
            $content = http_build_query($post);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        $options = array(
            'http' => array(
                'method'        => strtoupper($method),
                'header'        => implode("\r\n", $headers) . "\r\n",
                'content'       => $content,
                'ignore_errors' => true,
                'timeout'       => 15,
            ),
        );

        $context = stream_context_create($options);
        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            throw new RuntimeException('characterization net: HTTP request failed: ' . $method . ' ' . $path);
        }

        $status = 0;
        if (isset($http_response_header) && isset($http_response_header[0])) {
            if (preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
                $status = (int) $m[1];
            }
        }

        return array('status' => $status, 'body' => $body);
    }

    private static function dbConfig()
    {
        $path = PTAH_CHARACTERIZATION_ROOT . '/application/config/database.php';
        $source = file_get_contents($path);

        if ($source === false) {
            throw new RuntimeException('characterization net: cannot read application/config/database.php');
        }

        $extract = function ($key) use ($source) {
            if (!preg_match('/\'' . preg_quote($key, '/') . '\'\s*=>\s*\'([^\']*)\'/', $source, $m)) {
                throw new RuntimeException('characterization net: cannot locate "' . $key . '" in database.php');
            }
            return $m[1];
        };

        return array(
            'hostname' => $extract('hostname'),
            'username' => $extract('username'),
            'password' => $extract('password'),
            'database' => $extract('database'),
        );
    }

    private static function connectDb()
    {
        $config = self::dbConfig();

        $mysqli = @mysqli_connect($config['hostname'], $config['username'], $config['password'], $config['database']);
        if ($mysqli === false) {
            throw new RuntimeException('characterization net: cannot reach the frozen MySQL instance: ' . mysqli_connect_error());
        }

        $mysqli->set_charset('utf8');

        return $mysqli;
    }

    private function clearMessages()
    {
        if (!self::$db->query('DELETE FROM messages')) {
            throw new RuntimeException('characterization net: failed to clear messages between cases: ' . self::$db->error);
        }
    }

    private function seedMessage($name, $email, $message, $receivedOn)
    {
        $stmt = self::$db->prepare('INSERT INTO messages (name, email, message, received_on) VALUES (?, ?, ?, ?)');
        if ($stmt === false) {
            throw new RuntimeException('characterization net: seed prepare failed: ' . self::$db->error);
        }

        $stmt->bind_param('ssss', $name, $email, $message, $receivedOn);
        if (!$stmt->execute()) {
            throw new RuntimeException('characterization net: seed insert failed: ' . $stmt->error);
        }

        $stmt->close();
    }

    private function countMessages()
    {
        $result = self::$db->query('SELECT COUNT(*) AS c FROM messages');
        if ($result === false) {
            throw new RuntimeException('characterization net: count query failed: ' . self::$db->error);
        }

        $row = $result->fetch_assoc();

        return (int) $row['c'];
    }

    private function fetchMessages()
    {
        $result = self::$db->query('SELECT name, email, message, received_on FROM messages ORDER BY received_on DESC');
        if ($result === false) {
            throw new RuntimeException('characterization net: list query failed: ' . self::$db->error);
        }

        $rows = array();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }
}
