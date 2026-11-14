<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

class CircuitBreakerService
{
    public function acquire(string $scope, int $cooldownSeconds): array
    {
        $store = Cache::store('redis');
        $stateKey = $this->key($scope, 'state');
        $state = $store->get($stateKey, 'closed');

        if ($state === 'open') {
            $openUntil = (int) $store->get($this->key($scope, 'open-until'), 0);
            if ($openUntil > now()->timestamp) {
                return ['allowed' => false, 'retry_after' => max(1, $openUntil - now()->timestamp)];
            }
        } elseif ($state === 'half_open') {
            $probeLock = $store->lock($this->key($scope, 'probe'), 45);
            if (! $probeLock->get()) {
                return ['allowed' => false, 'retry_after' => 2];
            }

            return ['allowed' => true, 'probe_lock' => $probeLock];
        } else {
            return ['allowed' => true, 'probe_lock' => null];
        }

        $probeLock = $store->lock($this->key($scope, 'probe'), 45);
        if (! $probeLock->get()) {
            return ['allowed' => false, 'retry_after' => 2];
        }

        $store->forever($stateKey, 'half_open');

        return ['allowed' => true, 'probe_lock' => $probeLock];
    }

    public function recordSuccess(string $scope, ?Lock $probeLock = null): void
    {
        $store = Cache::store('redis');
        $store->forget($this->key($scope, 'failures'));
        $store->forget($this->key($scope, 'open-until'));
        $store->put($this->key($scope, 'state'), 'closed');
        $probeLock?->release();
    }

    public function recordFailure(
        string $scope,
        int $threshold,
        int $cooldownSeconds,
        ?Lock $probeLock = null
    ): void {
        $store = Cache::store('redis');

        if ($probeLock !== null) {
            $this->open($scope, $cooldownSeconds);
            $probeLock->release();

            return;
        }

        $failureKey = $this->key($scope, 'failures');
        $store->add($failureKey, 0, max(1, $cooldownSeconds * 2));
        $failures = $store->increment($failureKey);

        if ($failures >= $threshold) {
            $this->open($scope, $cooldownSeconds);
        }
    }

    public function cancelProbe(string $scope, ?Lock $probeLock): void
    {
        if ($probeLock === null) {
            return;
        }

        $store = Cache::store('redis');
        $store->forever($this->key($scope, 'state'), 'open');
        $store->put($this->key($scope, 'open-until'), now()->addSeconds(5)->timestamp, 65);
        $probeLock->release();
    }

    public function state(string $scope): string
    {
        $store = Cache::store('redis');
        $state = $store->get($this->key($scope, 'state'), 'closed');

        if ($state === 'open' && (int) $store->get($this->key($scope, 'open-until'), 0) <= now()->timestamp) {
            return 'HALF_OPEN';
        }

        return strtoupper($state);
    }

    public function reset(string $scope): void
    {
        $store = Cache::store('redis');
        foreach (['state', 'open-until', 'failures'] as $suffix) {
            $store->forget($this->key($scope, $suffix));
        }
        $store->lock($this->key($scope, 'probe'), 1)->forceRelease();
    }

    private function open(string $scope, int $cooldownSeconds): void
    {
        $store = Cache::store('redis');
        $store->forget($this->key($scope, 'failures'));
        $store->forever($this->key($scope, 'state'), 'open');
        $store->put(
            $this->key($scope, 'open-until'),
            now()->addSeconds($cooldownSeconds)->timestamp,
            $cooldownSeconds + 86400
        );
    }

    private function key(string $scope, string $suffix): string
    {
        return 'hookbytes:circuit:'.hash('sha256', $scope).':'.$suffix;
    }
}
