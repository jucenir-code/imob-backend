#!/usr/bin/env python3
"""Exercise the built image with an isolated MySQL database and native web session."""
import http.cookiejar
import json
import os
from pathlib import Path
import secrets
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

image = sys.argv[1] if len(sys.argv) > 1 else 'cci-web-docker-check:20260908'
name = 'cci-smoke-' + secrets.token_hex(4)
network, database, app = name + '-net', name + '-db', name + '-app'

def docker(*args):
    return subprocess.check_output(['docker', *args], text=True).strip()

def wait_for(callback, description, seconds=120):
    for _ in range(seconds):
        try:
            result = callback()
            if result:
                return result
        except (OSError, urllib.error.URLError, subprocess.CalledProcessError):
            pass
        time.sleep(1)
    raise RuntimeError('Timed out: ' + description)

try:
    docker('network', 'create', network)
    with tempfile.TemporaryDirectory(prefix='cci-docker-smoke-') as directory:
        env = Path(directory) / 'container.env'
        password = secrets.token_urlsafe(24)
        import base64
        key = base64.b64encode(secrets.token_bytes(32)).decode()
        env.write_text('\n'.join([
            'MYSQL_DATABASE=cci_smoke', 'MYSQL_USER=cci_smoke',
            'MYSQL_PASSWORD=' + password, 'MYSQL_ROOT_PASSWORD=' + secrets.token_urlsafe(24),
            'APP_ENV=production', 'APP_DEBUG=false', 'APP_KEY=base64:' + key,
            'APP_URL=https://cci.test', 'API_VERSION=v1',
            'DB_CONNECTION=mysql', 'DB_HOST=' + database, 'DB_DATABASE=cci_smoke',
            'DB_USERNAME=cci_smoke', 'DB_PASSWORD=' + password,
            'CACHE_DRIVER=file', 'SESSION_DRIVER=file', 'SESSION_SECURE_COOKIE=false',
            'QUEUE_CONNECTION=database', 'LOG_CHANNEL=stderr',
        ]) + '\n')
        env.chmod(0o600)
        docker('run', '-d', '--name', database, '--network', network,
               '--tmpfs', '/var/lib/mysql', '--env-file', str(env),
               os.environ.get('CCI_SMOKE_MYSQL_IMAGE', 'mysql:8.0'))
        docker('run', '-d', '--name', app, '--network', network,
               '--platform', 'linux/amd64', '--env-file', str(env),
               '-p', '127.0.0.1::80', image)
    port = json.loads(docker('inspect', app))[0]['NetworkSettings']['Ports']['80/tcp'][0]['HostPort']
    origin = 'http://127.0.0.1:' + port
    browser = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(path, payload=None, csrf=None, method=None):
        headers = {'Accept': 'application/json' if path.startswith(('/web/', '/session/', '/api/')) or payload is not None else 'text/html'}
        if csrf:
            headers['X-CSRF-TOKEN'] = csrf
        body = None
        if payload is not None:
            headers['Content-Type'] = 'application/json'
            body = json.dumps(payload).encode()
        return browser.open(urllib.request.Request(origin + path, data=body, headers=headers, method=method), timeout=5)

    wait_for(lambda: request('/login').status == 200, 'Laravel HTTP readiness')
    login = request('/login').read().decode()
    assert 'id="app"' in login, 'Vue shell missing'
    assert json.load(request('/api/v1/health'))['status'] == 'ok'
    assert json.load(request('/manifest.webmanifest'))['display'] == 'standalone'
    assert request('/sw.js').status == 200
    import re
    assets = re.findall(r'(?:src|href)="([^"]*/build/assets/[^"]+)"', login)
    assert assets, 'Compiled Vite assets missing'
    for asset in assets:
        assert request(urllib.parse.urlparse(asset).path).status == 200
    assert 'node_modules' not in docker('exec', app, 'ls', '/var/www/html')
    docker('exec', app, 'test', '!', '-f', '/var/www/html/.env.testing')
    docker('exec', app, 'test', '!', '-d', '/var/www/html/test-results')
    print('PASS: HTTP, Vue assets, API health, manifest and service worker', flush=True)

    seed = '''require "vendor/autoload.php"; $app = require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
App\\Models\\User::create(["name"=>"Docker Smoke", "email"=>"smoke@cci.test",
"password"=>Illuminate\\Support\\Facades\\Hash::make("smoke-test-only-123"),
"role"=>"admin", "status"=>"active", "is_approved"=>true]);'''
    docker('exec', app, 'php', '-r', seed)
    csrf = json.load(request('/session/csrf'))['token']
    assert request('/login', {'email': 'smoke@cci.test', 'password': 'smoke-test-only-123'}, csrf).status == 200
    assert json.load(request('/web/profile'))['data']['email'] == 'smoke@cci.test'
    assert request('/web/admin/users').status == 200
    assert request('/app/imoveis').status == 200
    print('PASS: Native session, protected web and Linux admin controller', flush=True)
    push_config = json.load(request('/web/push/config'))
    assert push_config['enabled'] and len(push_config['public_key']) == 87
    assert 'privateKey' not in push_config
    csrf = json.load(request('/session/csrf'))['token']
    subscription = {'endpoint': 'https://fcm.googleapis.com/fcm/send/docker-smoke', 'keys': {'p256dh': push_config['public_key'], 'auth': 'AAAAAAAAAAAAAAAAAAAAAA'}}
    assert request('/web/push/subscriptions', subscription, csrf).status == 200
    assert request('/web/push/subscriptions', {'endpoint': subscription['endpoint']}, csrf, 'DELETE').status == 204
    print('PASS: Web Push key generation and authenticated enrollment/removal', flush=True)
    wait_for(lambda: docker('exec', app, 'supervisorctl', '-c', '/etc/supervisord.conf', 'status').count('RUNNING') == 4, 'all Supervisor processes', 30)
    print('PASS: Nginx, PHP-FPM, queue worker and scheduler', flush=True)
    with tempfile.TemporaryDirectory(prefix='cci-queue-smoke-') as directory:
        job = Path(directory) / 'DockerSmokeJob.php'
        job.write_text(r"""<?php
namespace App\Jobs;
class DockerSmokeJob implements \Illuminate\Contracts\Queue\ShouldQueue {
    public function handle(): void {
        file_put_contents(storage_path('framework/docker-queue-smoke'), 'ok');
    }
}
""")
        docker('exec', app, 'mkdir', '-p', '/var/www/html/app/Jobs')
        docker('cp', str(job), app + ':/var/www/html/app/Jobs/DockerSmokeJob.php')
    enqueue = r'''require "vendor/autoload.php"; $app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Queue::push(new App\Jobs\DockerSmokeJob);'''
    docker('exec', app, 'php', '-r', enqueue)
    wait_for(lambda: docker('exec', app, 'cat', '/var/www/html/storage/framework/docker-queue-smoke') == 'ok', 'database queue job', 30)
    print('PASS: Database queue consumes a real job', flush=True)
    docker('restart', app)
    # Docker may allocate a different ephemeral host port after restart.
    port = json.loads(docker('inspect', app))[0]['NetworkSettings']['Ports']['80/tcp'][0]['HostPort']
    origin = 'http://127.0.0.1:' + port
    wait_for(lambda: request('/web/profile').status == 200, 'session after restart')
    assert json.load(request('/web/push/config'))['public_key'] == push_config['public_key']
    print('PASS: Session and Web Push identity survive restart with the same APP_KEY', flush=True)
except Exception:
    try:
        print(docker('logs', '--tail', '60', app), file=sys.stderr)
    except Exception:
        pass
    raise
finally:
    for container in [app, database]:
        subprocess.run(['docker', 'rm', '-f', container], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    subprocess.run(['docker', 'network', 'rm', network], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
