<?php

namespace JothamLec\MarketingToolkit\Http\Controllers\CP;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use JothamLec\MarketingToolkit\Cp\RecordActions;
use JothamLec\MarketingToolkit\NotFound\MissingPath;
use JothamLec\MarketingToolkit\Redirects\Redirect;
use Statamic\Actions\Action;
use Statamic\Facades\Site;
use Statamic\Http\Controllers\CP\ActionController as StatamicActionController;

/**
 * Runs Statamic actions on the redirects and 404 listings. The listing says
 * which it is in the action context.
 */
class ActionController extends StatamicActionController
{
    /**
     * Only the addon's actions run here: Statamic's `run()` would run any
     * registered action on our rows, and asks only that action to authorize.
     * The class the handle resolves to is checked, not the handle: Statamic
     * maps handles to classes and the last registration wins, so another
     * addon's action could take one of ours.
     */
    public function run(Request $request)
    {
        $action = $request->input('action');
        $class = is_string($action) ? app('statamic.actions')->get($action) : null;

        abort_unless(in_array($class, RecordActions::ACTIONS, true), 403);

        return parent::run($request);
    }

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

    /**
     * The selected rows, from those the listing shows this user: a 404 row on
     * the selected site, a redirect on a site they may work on. Any other is
     * as if it didn't exist.
     */
    protected function getSelectedItems($items, $context)
    {
        $query = ($context['type'] ?? null) === '404s'
            ? MissingPath::query()->shownOn(Site::selected()->handle())
            : Redirect::query()->accessible();
        $selected = $query->whereIn('id', $items->all())->get();

        abort_if($selected->count() < $items->unique()->count(), 404);

        return $selected;
    }
}
