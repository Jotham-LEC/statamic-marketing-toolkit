<?php

namespace JothamLec\MarketingToolkit\Http\Controllers\CP;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use JothamLec\MarketingToolkit\Cp\RecordActions;
use JothamLec\MarketingToolkit\NotFound\MissingPath;
use JothamLec\MarketingToolkit\Redirects\Redirect;
use Statamic\Actions\Action;
use Statamic\Http\Controllers\CP\ActionController as StatamicActionController;

/**
 * Runs Statamic actions on the redirects and 404 listings. The listing says
 * which it is in the action context.
 */
class ActionController extends StatamicActionController
{
    /**
     * Only the addon's actions; see RecordActions.
     *
     * @return Collection<int, Action>
     */
    public function bulkActions(Request $request)
    {
        $data = $request->validate([
            'selections' => 'required|array',
            'context' => 'sometimes',
        ]);

        $context = $data['context'] ?? [];

        return RecordActions::for($this->getSelectedItems(collect($data['selections']), $context), $context);
    }

    protected function getSelectedItems($items, $context)
    {
        $model = ($context['type'] ?? null) === '404s' ? MissingPath::class : Redirect::class;

        return $model::query()->whereIn('id', $items->all())->get();
    }
}
