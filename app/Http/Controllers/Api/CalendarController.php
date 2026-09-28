<?php

namespace App\Http\Controllers\Api;

use App\Dav\CalendarService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    protected function service(Request $request): CalendarService
    {
        return CalendarService::forUser($request->user());
    }

    public function calendars(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service($request)->calendars()]);
    }

    public function events(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'calendar' => ['nullable', 'string'],
        ]);

        $service = $this->service($request);
        $start = CarbonImmutable::parse($data['start']);
        $end = CarbonImmutable::parse($data['end']);

        $calendars = $service->calendars();
        if (! empty($data['calendar'])) {
            $calendars = array_values(array_filter($calendars, fn ($c) => $c['id'] === $data['calendar']));
        }

        $events = [];
        foreach ($calendars as $calendar) {
            foreach ($service->events($calendar['href'], $start, $end) as $event) {
                $events[] = $event + ['color' => $calendar['color'], 'calendar_name' => $calendar['name']];
            }
        }
        usort($events, fn ($a, $b) => strcmp($a['start'], $b['start']));

        return response()->json(['data' => $events, 'calendars' => $calendars]);
    }

    protected function rules(): array
    {
        return [
            'summary' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'location' => ['nullable', 'string', 'max:500'],
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
            'all_day' => ['boolean'],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules() + ['calendar_id' => ['required', 'string']]);
        $href = CalendarService::decode($data['calendar_id']);
        abort_unless($href !== '', 422, 'Invalid calendar.');

        return response()->json(['data' => $this->service($request)->create($href, $data)], 201);
    }

    public function update(Request $request, string $event): JsonResponse
    {
        $data = $request->validate($this->rules());
        $href = CalendarService::decode($event);
        abort_unless(str_ends_with($href, '.ics'), 404);

        return response()->json(['data' => $this->service($request)->update($href, $data)]);
    }

    public function destroy(Request $request, string $event): JsonResponse
    {
        $href = CalendarService::decode($event);
        abort_unless(str_ends_with($href, '.ics'), 404);
        $this->service($request)->delete($href);

        return response()->json(['message' => 'Deleted.']);
    }
}
