<?php

class ZonesController extends BaseController
{
    public function getAddSlave()
    {
        $domain = new Domain();
        $usersList = User::all()->lists('username', 'username');

        return View::make('zones.edit')
            ->withDomain($domain)
            ->with('usersList', $usersList);
    }

    public function postAddSlave()
    {
        $post = Input::all();

        $validator = Validator::make($post, Domain::$createSlaveRules);

        if ($validator->passes()) {
            $domain = new Domain();
            $domain->name = $post['name'];
            if (!empty($post['master'])) {
                $domain->master = $post['master'];
            }
            $domain->type = 'SLAVE';
            $domain->account = $post['account'];
            $saved = $domain->save();

            if ($saved === true) {
                return Redirect::to('/dashboard')
                    ->withSuccess('Slave zone added');
            } elseif ($saved instanceof Domain) {
                return Redirect::back()
                    ->withInput()
                    ->withErrors('The slave zone (ip and hostname) exists');
            } else {
                return Redirect::back()
                    ->withInput()
                    ->withErrors('Cant save the slave zone :/');
            }
        } else {
            return Redirect::back()
                ->withInput()
                ->withErrors($validator->errors()->all());
        }
    }

    public function getTemplates()
    {
        $templates = [];

        return View::make('zones.templates')
            ->withTemplates($templates);
    }

    public function getAddTemplate()
    {
        $template = new ZoneTemplate();

        return View::make('zones.edit-templates')
            ->withTemplate($template);
    }

    public function postAddTemplate()
    {
        $post = Input::all();

        $validator = Validator::make($post, ZoneTemplate::$createRules);

        if ($validator->passes()) {
            DB::beginTransaction();
            try {

                $zoneTemplate = new ZoneTemplate();
                $zoneTemplate->name = array_get($post, 'name');
                $zoneTemplate->description = array_get($post, 'description');
                $zoneTemplate->user_id = Auth::user()->id;
                $saved = $zoneTemplate->save();

                $zoneTemplateRecords = [];

                foreach ($post['record_names'] as $key=>$record) {
                    $recordValidator = Validator::make([
                        'template_id' => $zoneTemplate->id,
                        'name'  => $post['record_names'][$key],
                        'type' => $post['record_types'][$key],
                        'content' => $post['record_contents'][$key],
                        'ttl' => intval($post['record_ttls'][$key]),
                        'priority' => intval($post['record_priorities'][$key]),
                    ], ZoneTemplateRecord::getCreateRules());

                    if($recordValidator->passes()) {
                        $zoneTemplateRecord = new ZoneTemplateRecord([
                            'template_id' => $zoneTemplate->id,
                            'name'  => $post['record_names'][$key],
                            'type' => $post['record_types'][$key],
                            'content' => $post['record_contents'][$key],
                            'ttl' => intval($post['record_ttls'][$key]),
                            'priority' => intval($post['record_priorities'][$key]),
                        ]);

                        $zoneTemplateRecords[] = $zoneTemplateRecord;
                    }
                }

                $zoneTemplate->records()->saveMany($zoneTemplateRecords);

                DB::commit();
            } catch (\Exception $ex) {
                DB::rollback();

                return Redirect::back()
                    ->withInput()
                    ->withErrors($ex->getMessage());
            }

            return Redirect::to('/zones/templates')
                ->withSuccess('Template added');

        } else {
            return Redirect::back()
                ->withInput()
                ->withErrors($validator->errors()->all());
        }
    }
}