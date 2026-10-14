<?php

namespace Tests\Feature;

use App\Support\ProfilePhoto;
use App\Support\RegistrarSession;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Changing your own password and profile photo (auth.php actions).
 *
 * Runs against a separate MySQL database (mcu_nex_test, created if missing)
 * so the real mcu_nex data is never touched. The test user gets a high id so
 * its photo file can't collide with a real user's.
 */
class OwnAccountTest extends TestCase
{
    private const TEST_DB = 'mcu_nex_test';

    private const TEST_USER_ID = 900001;

    private int $userId = self::TEST_USER_ID;

    protected function setUp(): void
    {
        parent::setUp();

        $mysql = config('database.connections.mysql');
        try {
            $pdo = new \PDO('mysql:host='.$mysql['host'].';port='.$mysql['port'], $mysql['username'], $mysql['password']);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.self::TEST_DB.'`');
        } catch (\PDOException $e) {
            $this->markTestSkipped('MySQL is not running: '.$e->getMessage());
        }
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => self::TEST_DB]);
        DB::purge('mysql');
        foreach (['transaction_logs', 'user_programs', 'programs', 'users', 'colleges'] as $table) {
            Schema::dropIfExists($table);
        }

        // just the tables these actions touch
        Schema::create('colleges', function (Blueprint $t) {
            $t->increments('college_id');
            $t->string('college_name');
        });
        Schema::create('users', function (Blueprint $t) {
            $t->increments('user_id');
            $t->string('full_name');
            $t->string('email');
            $t->string('password_hash');
            $t->string('role');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('college_id')->nullable();
        });
        Schema::create('programs', function (Blueprint $t) {
            $t->increments('program_id');
            $t->string('program_name');
            $t->unsignedInteger('department_id')->nullable();
            $t->boolean('is_active')->default(true);
        });
        Schema::create('user_programs', function (Blueprint $t) {
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('program_id');
        });
        Schema::create('transaction_logs', function (Blueprint $t) {
            $t->increments('log_id');
            $t->unsignedInteger('user_id')->nullable();
            $t->string('actor_name');
            $t->string('actor_role');
            $t->string('action');
            $t->string('area');
            $t->string('summary');
        });

        DB::table('users')->insert([
            'user_id' => self::TEST_USER_ID,
            'full_name' => 'Test Dean',
            'email' => 'dean@test.example',
            'password_hash' => password_hash('old-pass', PASSWORD_DEFAULT),
            'role' => 'dean',
            'is_active' => 1,
        ]);
        RegistrarSession::login($this->userId);
    }

    protected function tearDown(): void
    {
        ProfilePhoto::delete(self::TEST_USER_ID);
        RegistrarSession::logout();
        if (config('database.connections.mysql.database') === self::TEST_DB) {
            foreach (['transaction_logs', 'user_programs', 'programs', 'users', 'colleges'] as $table) {
                Schema::dropIfExists($table);
            }
        }
        parent::tearDown();
    }

    private function auth(array $body)
    {
        return $this->postJson('/api/auth.php', $body);
    }

    public function test_any_role_can_change_their_own_password(): void
    {
        $this->auth(['action' => 'change_password', 'currentPassword' => 'old-pass', 'newPassword' => 'new-pass', 'newPasswordConfirm' => 'new-pass'])
            ->assertOk()->assertJson(['ok' => true]);

        $hash = DB::table('users')->where('user_id', $this->userId)->value('password_hash');
        $this->assertTrue(password_verify('new-pass', $hash));
        $this->assertSame('Changed their password.', DB::table('transaction_logs')->value('summary'));
    }

    public function test_password_change_is_refused_with_a_clear_reason(): void
    {
        $cases = [
            [['wrong', 'new-pass', 'new-pass'], 'Your current password is incorrect.'],
            [['old-pass', 'abc', 'abc'], 'Use a new password of at least 5 characters.'],
            [['old-pass', 'new-pass', 'other-pass'], 'The new passwords do not match.'],
            [['old-pass', 'old-pass', 'old-pass'], 'Choose a password different from your current one.'],
        ];
        foreach ($cases as [[$cur, $new, $confirm], $message]) {
            $this->auth(['action' => 'change_password', 'currentPassword' => $cur, 'newPassword' => $new, 'newPasswordConfirm' => $confirm])
                ->assertStatus(400)->assertJson(['ok' => false, 'error' => $message]);
        }
        $hash = DB::table('users')->where('user_id', $this->userId)->value('password_hash');
        $this->assertTrue(password_verify('old-pass', $hash));
    }

    public function test_photo_can_be_set_shown_and_removed(): void
    {
        // a real 1x1 PNG
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $res = $this->auth(['action' => 'set_photo', 'photo' => $png])->assertOk();
        $url = $res->json('user.photoUrl');
        $this->assertNotNull($url);

        $this->get(parse_url($url, PHP_URL_PATH))->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->auth(['action' => 'remove_photo'])->assertOk()->assertJsonPath('user.photoUrl', null);
        $this->get('/api/avatar/u-'.$this->userId)->assertNotFound();
    }

    public function test_photo_upload_rejects_files_that_are_not_images(): void
    {
        $this->auth(['action' => 'set_photo', 'photo' => 'data:image/png;base64,'.base64_encode('not a png')])
            ->assertStatus(400)->assertJson(['error' => 'That file is not a JPG, PNG, or WebP photo.']);
        $this->auth(['action' => 'set_photo', 'photo' => 'data:text/html;base64,'.base64_encode('<b>hi</b>')])
            ->assertStatus(400)->assertJson(['error' => 'Upload a JPG, PNG, or WebP photo.']);
        $this->assertNull(ProfilePhoto::path($this->userId));
    }

    public function test_photos_are_only_served_to_signed_in_users(): void
    {
        RegistrarSession::logout();
        $this->get('/api/avatar/u-'.$this->userId)->assertStatus(401);
    }
}
