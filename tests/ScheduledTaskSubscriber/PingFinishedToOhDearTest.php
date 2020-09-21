<?php

namespace Spatie\ScheduleMonitor\Tests\ScheduledTaskSubscriber;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\ScheduleMonitor\Jobs\PingOhDearJob;
use Spatie\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem;
use Spatie\ScheduleMonitor\Tests\TestCase;
use Spatie\TestTime\TestTime;

class PingFinishedToOhDearTest extends TestCase
{
    private MonitoredScheduledTaskLogItem $monitoredScheduledTaskLogItem;

    private array $meta;

    public function setUp(): void
    {
        parent::setUp();

        TestTime::freeze('Y-m-d H:i:s', '2020-01-01 00:00:00');

        Http::fake([
            'ping.ohdear.app/*' => Http::response('ok', 200),
        ]);

        $this->meta = [
            'runtime' => 12.34,
            'exit_code' => 123,
            'memory' => 12345,
        ];

        $this->monitoredScheduledTaskLogItem = factory(MonitoredScheduledTaskLogItem::class)->create([
            'type' => MonitoredScheduledTaskLogItem::TYPE_FINISHED,
            'meta' => $this->meta,
        ]);
    }

    /** @test */
    public function it_can_ping_oh_dear_when_a_scheduled_task_finishes()
    {
        dispatch(new PingOhDearJob($this->monitoredScheduledTaskLogItem));

        $this->assertEquals(
            $this->monitoredScheduledTaskLogItem->monitoredScheduledTask->refresh()->last_pinged_at->format('Y-m-d H:i:s'),
            now()->format('Y-m-d H:i:s'),
        );

        Http::assertSent(function (Request $request) {
            $this->assertEquals(
                $request->url(),
                $this->monitoredScheduledTaskLogItem->monitoredScheduledTask->ping_url . '/finished'
            );

            $this->assertEquals($this->meta, $request->data());

            return true;
        });
    }
}
