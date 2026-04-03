<?php

namespace App\Livewire;

use App\Models\HelpArticle;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class HelpSearch extends Component
{
    public string $query = '';

    public function getResultsProperty(): array
    {
        $term = trim($this->query);

        if ($term === '') {
            return [];
        }

        $like = '%' . $term . '%';

        // Ranked search: title > tags > description > content
        // Use a CASE expression to assign priority, then sort by it.
        return HelpArticle::query()
            ->where(function ($q) use ($like) {
                $q->where('title', 'ilike', $like)
                    ->orWhereRaw("EXISTS (SELECT 1 FROM jsonb_array_elements_text(tags::jsonb) AS t WHERE t ILIKE ?)", [$like])
                    ->orWhere('description', 'ilike', $like)
                    ->orWhere('content', 'ilike', $like);
            })
            ->selectRaw("*, CASE
                WHEN title ILIKE ? THEN 1
                WHEN EXISTS (SELECT 1 FROM jsonb_array_elements_text(tags::jsonb) AS t WHERE t ILIKE ?) THEN 2
                WHEN description ILIKE ? THEN 3
                ELSE 4
            END AS search_rank", [$like, $like, $like])
            ->orderBy('search_rank')
            ->orderBy('title')
            ->limit(8)
            ->get()
            ->toArray();
    }

    public function render(): View
    {
        return view('livewire.help-search', [
            'results' => $this->results,
        ]);
    }
}
