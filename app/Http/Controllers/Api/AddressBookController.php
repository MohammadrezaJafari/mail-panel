<?php

namespace App\Http\Controllers\Api;

use App\Dav\AddressBookService;
use App\Dav\CalendarService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressBookController extends Controller
{
    protected function service(Request $request): AddressBookService
    {
        return AddressBookService::forUser($request->user());
    }

    public function books(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service($request)->books()]);
    }

    public function contacts(Request $request): JsonResponse
    {
        $data = $request->validate(['book' => ['nullable', 'string'], 'q' => ['nullable', 'string', 'max:100']]);
        $service = $this->service($request);

        $books = $service->books();
        if (! empty($data['book'])) {
            $books = array_values(array_filter($books, fn ($b) => $b['id'] === $data['book']));
        }

        $contacts = [];
        foreach ($books as $book) {
            $contacts = [...$contacts, ...$service->contacts($book['href'])];
        }

        if (filled($data['q'] ?? null)) {
            $q = mb_strtolower($data['q']);
            $contacts = array_values(array_filter($contacts, fn ($c) => str_contains(mb_strtolower($c['name'].' '.$c['org'].' '.implode(' ', array_column($c['emails'], 'value'))), $q)));
        }

        usort($contacts, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return response()->json(['data' => $contacts, 'books' => $books]);
    }

    protected function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:200'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'emails' => ['nullable', 'array', 'max:10'],
            'emails.*.value' => ['required', 'email'],
            'emails.*.type' => ['nullable', 'string', 'max:20'],
            'phones' => ['nullable', 'array', 'max:10'],
            'phones.*.value' => ['required', 'string', 'max:50'],
            'phones.*.type' => ['nullable', 'string', 'max:20'],
            'org' => ['nullable', 'string', 'max:200'],
            'title' => ['nullable', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules() + ['book_id' => ['required', 'string']]);
        if (blank($data['name'] ?? null) && blank($data['first_name'] ?? null) && blank($data['last_name'] ?? null)) {
            abort(422, 'A name is required.');
        }
        $href = CalendarService::decode($data['book_id']);
        abort_unless($href !== '', 422, 'Invalid address book.');

        return response()->json(['data' => $this->service($request)->create($href, $data)], 201);
    }

    public function update(Request $request, string $contact): JsonResponse
    {
        $data = $request->validate($this->rules());
        $href = CalendarService::decode($contact);
        abort_unless(str_ends_with($href, '.vcf'), 404);

        return response()->json(['data' => $this->service($request)->update($href, $data)]);
    }

    public function destroy(Request $request, string $contact): JsonResponse
    {
        $href = CalendarService::decode($contact);
        abort_unless(str_ends_with($href, '.vcf'), 404);
        $this->service($request)->delete($href);

        return response()->json(['message' => 'Deleted.']);
    }
}
