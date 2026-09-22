<?php

use App\Services\EnvFileWriter;

beforeEach(function () {
    $this->path = sys_get_temp_dir().'/env_writer_test_'.uniqid().'.env';
    file_put_contents($this->path, <<<ENV
    APP_NAME=VisitorPass
    DB_HOST=127.0.0.1
    DB_PASSWORD=
    ENV);
});

afterEach(function () {
    if (file_exists($this->path)) {
        unlink($this->path);
    }
});

test('it replaces an existing key while leaving other lines untouched', function () {
    (new EnvFileWriter($this->path))->update(['DB_HOST' => '10.0.0.5']);

    $contents = file_get_contents($this->path);

    expect($contents)->toContain('DB_HOST=10.0.0.5');
    expect($contents)->toContain('APP_NAME=VisitorPass');
    expect($contents)->not->toContain('DB_HOST=127.0.0.1');
});

test('it adds a key that does not exist yet', function () {
    (new EnvFileWriter($this->path))->update(['DB_PORT' => '3306']);

    expect(file_get_contents($this->path))->toContain('DB_PORT=3306');
});

test('it quotes a value containing a space', function () {
    (new EnvFileWriter($this->path))->update(['APP_NAME' => 'Visitor Pass Pro']);

    expect(file_get_contents($this->path))->toContain('APP_NAME="Visitor Pass Pro"');
});

test('it quotes a value containing a hash and escapes embedded quotes', function () {
    (new EnvFileWriter($this->path))->update(['DB_PASSWORD' => 'p#ss"word']);

    expect(file_get_contents($this->path))->toContain('DB_PASSWORD="p#ss\\"word"');
});

test('it leaves a plain value unquoted', function () {
    (new EnvFileWriter($this->path))->update(['DB_HOST' => '127.0.0.1']);

    expect(file_get_contents($this->path))->toContain('DB_HOST=127.0.0.1');
    expect(file_get_contents($this->path))->not->toContain('"127.0.0.1"');
});
