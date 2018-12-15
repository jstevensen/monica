<?php

namespace App\Services\Contact\Contact;

use App\Services\BaseService;
use App\Models\Contact\Contact;
use App\Models\Instance\SpecialDate;

class UpdateDeceasedInformation extends BaseService
{
    private $contact;

    /**
     * Get the validation rules that apply to the service.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'account_id'      => 'required|integer|exists:accounts,id',
            'contact_id'      => 'required|integer',
            'is_deceased' => 'required|boolean',
            'is_date_known' => 'required|boolean',
            'day' => 'nullable|integer',
            'month' => 'nullable|integer',
            'year' => 'nullable|integer',
            'add_reminder' => 'required|boolean',
        ];
    }

    /**
     * Update the information about the deceased date.
     *
     * @param array $data
     * @return SpecialDate|null
     */
    public function execute(array $data)
    {
        $this->validate($data);

        $this->contact = Contact::where('account_id', $data['account_id'])
            ->findOrFail($data['contact_id']);

        $this->contact->removeSpecialDate('deceased_date');

        return $this->manageDeceasedDate($data);
    }

    /**
     * Update deceased date information depending on the type of information.
     *
     * @param array $data
     * @return SpecialDate|null
     */
    private function manageDeceasedDate(array $data)
    {
        if ($data['is_deceased'] == false) {
            // remove all information about deceased date in the DB
            $this->contact->is_dead = false;
            $this->contact->save();

            return;
        }

        $this->contact->is_dead = true;
        $this->contact->save();

        if ($data['is_date_known'] == false) {
            return;
        }

        $specialDate = $this->exact($data);

        return $specialDate;
    }

    /**
     * Case where we have a year, month and day for the birthday.
     *
     * @param  array  $data
     * @return void
     */
    private function exact(array $data)
    {
        $specialDate = $specialDate = $this->contact->setSpecialDate(
            'deceased_date',
            (is_null($data['year']) ? 0 : $data['year']),
            $data['month'],
            $data['day']
        );

        $this->setReminder($data, $specialDate);

        return $specialDate;
    }

    /**
     * Set a reminder for the given special date, if required.
     *
     * @param array  $data
     * @param SpecialDate $specialDate
     */
    private function setReminder(array $data, SpecialDate $specialDate)
    {
        if ($data['add_reminder'] == true) {
            $specialDate->setReminder(
                'year',
                1,
                trans(
                    'people.deceased_reminder_title',
                    ['name' => $this->contact->first_name]
                )
            );
        }
    }
}