<?php

namespace App\Services;

use App\Models\RouteListing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RouteListingService
{
    /**
     * @return LengthAwarePaginator<int, RouteListing>
     */
    public function paginate(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        $search = trim((string) $search);

        return RouteListing::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('city', 'like', "%{$search}%")
                        ->orWhere('about', 'like', "%{$search}%");
                });
            })
            ->latest('listed_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Active listings in the shape the member "Established locations & routes for sale" page renders.
     *
     * @return list<array{id: string, city: string, about: string, locs: int, machines: int, rev: int, ask: int, fin: string, listed: string}>
     */
    public function listActiveForMembers(): array
    {
        return RouteListing::query()
            ->where('is_active', true)
            ->latest('listed_at')
            ->latest('id')
            ->get()
            ->map(fn (RouteListing $listing): array => [
                'id' => 'R-'.$listing->id,
                'city' => $listing->city,
                'about' => $listing->about,
                'locs' => $listing->locations_count,
                'machines' => $listing->machines_count,
                'rev' => $listing->annual_revenue,
                'ask' => $listing->asking_price,
                'fin' => $listing->seller_financing ? 'Yes' : 'No',
                'listed' => $listing->listed_at->format('M d'),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): RouteListing
    {
        $data['is_active'] = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;
        $data['seller_financing'] = (bool) ($data['seller_financing'] ?? false);
        $data['listed_at'] = $data['listed_at'] ?? now()->toDateString();

        return RouteListing::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RouteListing $routeListing, array $data): RouteListing
    {
        foreach (['is_active', 'seller_financing'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $data[$flag] = (bool) $data[$flag];
            }
        }

        $routeListing->update($data);

        return $routeListing->refresh();
    }

    public function delete(RouteListing $routeListing): void
    {
        $routeListing->delete();
    }
}
