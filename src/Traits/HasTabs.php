<?php

declare(strict_types=1);

namespace siviwe191\NovaTabs\Traits;

use siviwe191\NovaTabs\Tabs;
use Illuminate\Support\Collection;
use Laravel\Nova\Contracts\BehavesAsPanel;
use Laravel\Nova\Fields\FieldCollection;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;


trait HasTabs
{
    /**
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Laravel\Nova\Fields\FieldCollection<int, \Laravel\Nova\Fields\Field>  $fields
     * @param  string  $label
     * @return Collection
     */
    protected function resolvePanelsFromFields(NovaRequest $request, FieldCollection $fields, string $label): Collection
    {
        [$defaultFields, $fieldsWithPanels] = $fields->each(function ($field) {
            $assignedPanel = is_object($field) ? data_get($field,'assignedPanel') : null;
            if ($field instanceof BehavesAsPanel && !($assignedPanel instanceof Tabs)) {
                $field->asPanel();
            }
        })->partition(function ($field) {
            $panel = is_object($field) ? data_get($field,'panel') : null;
            return empty($panel) || blank($panel);
        });

        $panels = $fieldsWithPanels->groupBy(function ($field) {
            return is_object($field) ? data_get($field,'panel','') : '';
        })->transform(function ($groupedFields, $name) {
            $name = (string)$name;

            $items = $groupedFields instanceof \Illuminate\Support\Collection
            ? $groupedFields->values()->all()
            : array_values($groupedFields);

            $fieldCollection = new FieldCollection($items);

            $firstField = $ietm[0] ?? null;

            $assignedPanel =  $firstField ? data_get($firstField, 'assignedPanel') : null;
            
            
            if ($assignedPanel instanceof Tabs) {
                return Tabs::mutate($name, $fieldCollection);
            }

            return Panel::mutate($name, $fieldCollection);
        })->toBase();

        if ($panels->where('component', 'tabs')->isEmpty()) {
            return $this->panelsWithDefaultLabel(
                $panels,
                $defaultFields->values(),
                $label
            );
        }

        [$relationshipUnderTabs, $panels] = $panels->partition(function ($panel) {
            if(!is_object($panel) || data_get($panel,'component') !== 'relationship-panel'){
                return false;
            }

            $fieldInPanel = data_get($panel->meta,'fields',[]);
            $firstField = $fieldInPanel[0] ?? null;

            return $firstField  && data_get($firstField ,'panel') instanceof Tabs;
            
        });

        $panels->transform(function ($panel, $key) use ($relationshipUnderTabs) {

            if (is_object($panel) && data_get($panel,'component') === 'tabs') {

                 $fieldInPanel = data_get($panel->meta,'fields',[]);
                 $firstField = $fieldInPanel[0] ?? null;
                 $assignedPanel = $firstField ?  data_get($firstField,'assignedPanel') :  null;


                if($firstField && $assignedPanel){
                foreach ($relationshipUnderTabs as $rel) {
                    $relFields = data_get($rel->meta, 'fields',[]);
                    $firstRelField = $relFields[0] ?? null;

                    if ($firstRelField && data_get($firstRelField,'panel') === $assignedPanel) {
                        $panel->meta['fields'][] = $firstRelField;
                    }
                }

                $panel->name = data_get($firstField, 'panel.name', $panel->name ?? '');
                $panel->showTitle = data_get($assignedPanel, 'showTitle', true);
                $panel->showToolbar = data_get($assignedPanel, 'showToolbar', false);
                $panel->slug = data_get($assignedPanel, 'slug', '');
                $panel->currentColor = data_get($assignedPanel, 'currentColor');
                $panel->bgColor = data_get($assignedPanel, 'bgColor');
                $panel->errorColor = data_get($assignedPanel, 'errorColor');
                $panel->retainTabPosition = data_get($assignedPanel, 'retainTabPosition', false);
            }
        }

            return $panel ?? null;
        });

        return $this->panelsWithDefaultLabel(
            $panels,
            $defaultFields->values(),
            $label
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Laravel\Nova\Panel>  $panels
     * @param  \Laravel\Nova\Fields\FieldCollection<int, \Laravel\Nova\Fields\Field>  $fields
     * @param  string  $label
     * @return \Illuminate\Support\Collection<int, \Laravel\Nova\Panel>
     */
    protected function panelsWithDefaultLabel(Collection $panels, FieldCollection $fields, string $label) : Collection
    {
        return $panels->values()
            ->when($panels->where('name', $label)->isEmpty(), function ($panels) use ($label, $fields) {
                return $fields->isNotEmpty()
                    ? $panels->prepend(Panel::make($label, $fields)->withMeta(['fields' => $fields]))
                    : $panels;
            })
            ->tap(function ($panels) use ($label): void {

                /**
                 * There can be no panels
                 * Preventing ->component or ->withToolbar() on null error
                 */
                if (!$panels->first()) {
                    return;
                }

                /**
                 * Default to ->withToolbar() if the first panel is not a Tabs
                 * Otherwise, we show the tabs component with the settings configured within it
                 * This check is necessary in case you ARE using Tabs component, but only for relation as a 2nd or 3rd instance
                 */
                $panels->first()->component !== 'tabs' ? $panels->first()->withToolbar() : $panels->where('component', 'tabs')->first();
            });
    }
}
