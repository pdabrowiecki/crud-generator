<?php

namespace Appzcoder\CrudGenerator\Commands;

use Illuminate\Console\Command;

class CrudViewCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crud:view
                            {name : The name of the Crud.}
                            {--fields= : The fields name for the form.}
                            {--path= : The name of the view path.}
                            {--namespace= : Namespace of the controller.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create views for the Crud.';

    protected function inputText($item)
    {
        if ($item['optional']) {
            $optional = '<div class="input-group-addon"><input type="checkbox" class="optional"></div>' . PHP_EOL;
        } else {
            $optional = '';
        }

        switch ($item['type']) {
            case 'string':
                return $optional
                     . "{!! Form::text('" . $item['name'] . "', null, ['class' => 'form-control']) !!}";

            case 'text':
                return $optional
                     . "{!! Form::textarea('" . $item['name'] . "', null, ['class' => 'form-control']) !!}";

            case 'boolean':
                return "{!! Form::checkbox('" . $item['name'] . "', null, null) !!}";

            case 'decimal':
            case 'integer':
                return $optional
                . "{!! Form::number('" . $item['name'] . "', null, ['class' => 'form-control']) !!}";

            case 'date':
                $field = "\$%%crudNameSingular%%";
                return $optional
                     . "{!! Form::date('" . $item['name'] . "', isset($field) ? $field->{$item['name']} : null, ['class' => 'form-control', 'data-provide' => 'datepicker']) !!}" . PHP_EOL
                     . '<span class="input-group-addon"><i class="fa fa-calendar"> </i></span>';

            case 'select':
                return $optional
                . "{!! Form::select('" . $item['name'] . "', array_map('trans', \${$item['name']}_options), null, ['class' => 'form-control']) !!}";

            case 'currency':
                return $optional
                     . "{!! Form::number('" . $item['name'] . "', null, ['class' => 'form-control']) !!}" . PHP_EOL
                . '<span class="input-group-addon">zł</span>';

            case 'password':
                return $optional
                     . "{!! Form::password('" . $item['name'] . "', null, ['class' => 'form-control']) !!}";

            case 'url':
                return $optional
                     . "{!! Form::url('" . $item['name'] . "', null, ['class' => 'form-control']) !!}" . PHP_EOL
                     . '<span class="input-group-addon"><i class="fa fa-external-link"></i></span>';

            case 'email':
                return $optional
                     . "{!! Form::email('" . $item['name'] . "', null, ['class' => 'form-control']) !!}";

            default:
                return $optional
                     . "{!! Form::text('" . $item['name'] . "', null, ['class' => 'form-control']) !!}";
        }
    }

    protected function showField($item)
    {
        $field = $item['name'];
        switch($item['type'])
        {
            case 'boolean':
                return "<td>$%%crudNameSingular%%->$field ? trans('%%crudNameSingular%%.yes') : trans('%%crudNameSingular%%.no')</td>";

            default:
                if ($item['optional'])
                {
                    return <<<EOB
@if ($%%crudNameSingular%%->$field)
    <td>{{ $%%crudNameSingular%%->$field }}</td>
@else
    <td class="text-muted">{{ trans('%%crudNameSingular%%.not_applicable') }}</td>
@endif
EOB;
                } else {
                    return "<td>{{ $%%crudNameSingular%%->$field }}</td>";
                }
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $crudName = strtolower($this->argument('name'));
        $crudNameCap = ucwords($crudName);
        $crudNameSingular = str_singular($crudName);
        $crudNameSingularCap = ucwords($crudNameSingular);
        $crudNamePlural = str_plural($crudName);
        $crudNamePluralCap = ucwords($crudNamePlural);

        $viewDirectory = base_path('resources/views/');
        if ($this->option('path')) {
            $userPath = $this->option('path');
            $path = $viewDirectory . $userPath . '/' . $crudName . '/';
        } else {
            $path = $viewDirectory . $crudName . '/';
        }

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $fields = $this->option('fields');
        $fieldsArray = explode(',', $fields);

        $formFields = array();
        $x = 0;
        foreach ($fieldsArray as $item) {
            $itemArray = explode(':', $item);
            $formFields[$x]['name'] = trim($itemArray[0]);
            $formFields[$x]['type'] = trim($itemArray[1]);
            $formFields[$x]['hidden'] = isset($itemArray[2]) && trim($itemArray[2]) == 'hidden';
            $formFields[$x]['optional'] = isset($itemArray[2]) && trim($itemArray[2]) == 'optional';
            $x++;
        }

        $formFieldsHtml = '';
        foreach ($formFields as $item) {
            //$label = ucwords(strtolower(str_replace('_', ' ', $item['name'])));
            $label = "trans('$crudNameSingular.label_{$item['name']}')";

            if ($item['hidden']) {
                $formFieldsHtml .= "{!! Form::hidden('{$item['name']}', null) !!}\n";
            } else {
                $formFieldsHtml .=
                    "@if(\$error = \$errors->default->first('{$item['name']}'))\n"
                    .  "<div class=\"form-group has-error\">"
                    .  '<label class="control-label col-sm-12" for="'. $item['name'].'"><i class="fa fa-times-circle-o"></i> {{ $error }}</label>'
                    . "@else\n"
                      .  "<div class=\"form-group\">"
                    . "@endif\n"
                    . "\n{!! Form::label('" . $item['name'] . "', " . $label . ". ': ', ['class' => 'col-sm-3 control-label']) !!}\n"
                    . "<div class=\"col-sm-9\">\n"
                    . '<div class="input-group col-sm-12">' . PHP_EOL
                        . $this->inputText($item) . PHP_EOL
                    . "</div></div>";
                $formFieldsHtml .= "\n</div>\n";
            }
        }

        // Form fields and label
        $formHeadingHtml = '';
        $formBodyHtml = '';
        $showRows = '';

        $i = 0;
        $labels = [];
        foreach ($formFields as $key => $value) {
            $field = $value['name'];
            $label = ucwords(str_replace('_', ' ', $field));
            $labels[] = "'label_$field' => '$label'";
            if ($value['hidden']) continue;

            $showRows .= "<tr>" . PHP_EOL
                      .  "    <th>{{ trans('%%crudName%%.label_$field') }}</th>" . PHP_EOL
                      .  "    {$this->showField($value)}" . PHP_EOL
                      .  "</tr>";

            $formHeadingHtml .= "<th>{{ trans('%%crudName%%.label_$field') }}</th>\n";

            if ($i == 0) {
                $formBodyHtml .= '<td><a href="{{ $item->url() }}">{{ $item->' . $field . ' }}</a></td>';
            } else {
                $formBodyHtml .= '<td>{{ $item->' . $field . ' }}</td>';
            }
            $formBodyHtml .= PHP_EOL;
            $i++;
        }


        $namespace = $this->option('namespace') ? $this->option('namespace') . '\\' : '';

        // For index.blade.php file
        $indexFile = __DIR__ . '/../stubs/index.blade.stub';
        $newIndexFile = $path . 'index.blade.php';
        if (!copy($indexFile, $newIndexFile)) {
            echo "failed to copy $indexFile...\n";
        } else {
            file_put_contents($newIndexFile, str_replace('%%namespace%%', $namespace, file_get_contents($newIndexFile)));
            file_put_contents($newIndexFile, str_replace('%%formHeadingHtml%%', $formHeadingHtml, file_get_contents($newIndexFile)));
            file_put_contents($newIndexFile, str_replace('%%formBodyHtml%%', $formBodyHtml, file_get_contents($newIndexFile)));
            file_put_contents($newIndexFile, str_replace('%%crudName%%', $crudName, file_get_contents($newIndexFile)));
            file_put_contents($newIndexFile, str_replace('%%crudNameCap%%', $crudNameCap, file_get_contents($newIndexFile)));
            file_put_contents($newIndexFile, str_replace('%%crudNamePlural%%', $crudNamePlural, file_get_contents($newIndexFile)));
            file_put_contents($newIndexFile, str_replace('%%crudNamePluralCap%%', $crudNamePluralCap, file_get_contents($newIndexFile)));
        }

        // For create.blade.php file
        $createFile = __DIR__ . '/../stubs/create.blade.stub';
        $newCreateFile = $path . 'create.blade.php';
        if (!copy($createFile, $newCreateFile)) {
            echo "failed to copy $createFile...\n";
        } else {
            file_put_contents($newCreateFile, str_replace('%%namespace%%', $namespace, file_get_contents($newCreateFile)));
            file_put_contents($newCreateFile, str_replace('%%formFieldsHtml%%', $formFieldsHtml, file_get_contents($newCreateFile)));
            file_put_contents($newCreateFile, str_replace('%%crudName%%', $crudName, file_get_contents($newCreateFile)));
            file_put_contents($newCreateFile, str_replace('%%crudNameSingular%%', $crudNameSingular, file_get_contents($newCreateFile)));
            file_put_contents($newCreateFile, str_replace('%%crudNameSingularCap%%', $crudNameSingularCap, file_get_contents($newCreateFile)));

        }

        // For edit.blade.php file
        $editFile = __DIR__ . '/../stubs/edit.blade.stub';
        $newEditFile = $path . 'edit.blade.php';
        if (!copy($editFile, $newEditFile)) {
            echo "failed to copy $editFile...\n";
        } else {
            file_put_contents($newEditFile, str_replace('%%namespace%%', $namespace, file_get_contents($newEditFile)));
            file_put_contents($newEditFile, str_replace('%%formFieldsHtml%%', $formFieldsHtml, file_get_contents($newEditFile)));
            file_put_contents($newEditFile, str_replace('%%crudNameCap%%', $crudNameCap, file_get_contents($newEditFile)));
            file_put_contents($newEditFile, str_replace('%%crudNameSingular%%', $crudNameSingular, file_get_contents($newEditFile)));
            file_put_contents($newEditFile, str_replace('%%crudNameSingularCap%%', $crudNameSingularCap, file_get_contents($newEditFile)));

        }

        // For show.blade.php file
        $showFile = __DIR__ . '/../stubs/show.blade.stub';
        $newShowFile = $path . 'show.blade.php';
        if (!copy($showFile, $newShowFile)) {
            echo "failed to copy $showFile...\n";
        } else {
            file_put_contents($newShowFile, str_replace('%%namespace%%', $namespace, file_get_contents($newShowFile)));
            file_put_contents($newShowFile, str_replace('%%showRows%%', $showRows, file_get_contents($newShowFile)));
            file_put_contents($newShowFile, str_replace('%%crudName%%', $crudName, file_get_contents($newShowFile)));
            file_put_contents($newShowFile, str_replace('%%crudNameSingular%%', $crudNameSingular, file_get_contents($newShowFile)));
            file_put_contents($newShowFile, str_replace('%%crudNameSingularCap%%', $crudNameSingularCap, file_get_contents($newShowFile)));
        }

        $langDirectory = base_path('resources/lang/en');

        if (!is_dir($langDirectory)) {
            mkdir($langDirectory, 0755, true);
        }

        $langFile = __DIR__ . '/../stubs/lang.stub';
        $newLangFile = $langDirectory . "/$crudNameSingular.php";
        if (!copy($langFile, $newLangFile)) {
            echo "failed to copy $langFile...\n";
        } else {
            file_put_contents($newLangFile, str_replace('%%crudNameSingular%%', $crudNameSingular, file_get_contents($newLangFile)));
            file_put_contents($newLangFile, str_replace('%%crudNameSingularCap%%', $crudNameSingularCap, file_get_contents($newLangFile)));
            file_put_contents($newLangFile, str_replace('%%crudNamePluralCap%%', $crudNamePluralCap, file_get_contents($newLangFile)));
            file_put_contents($newLangFile, str_replace('%%labels%%', implode(",\n    ", $labels), file_get_contents($newLangFile)));
        }

        // For layouts/master.blade.php file
        $layoutsDirPath = base_path('resources/views/layouts/');
        if (!is_dir($layoutsDirPath)) {
            mkdir($layoutsDirPath);
        }

        $layoutsFile = __DIR__ . '/../stubs/master.blade.stub';
        $newLayoutsFile = $layoutsDirPath . 'master.blade.php';

        if (!file_exists($newLayoutsFile)) {
            if (!copy($layoutsFile, $newLayoutsFile)) {
                echo "failed to copy $layoutsFile...\n";
            } else {
                file_get_contents($newLayoutsFile);
            }
        }

        $this->info('View created successfully.');

    }
}
