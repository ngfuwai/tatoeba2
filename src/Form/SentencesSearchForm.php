<?php

namespace App\Form;

use App\Model\CurrentUser;
use App\Model\Search;
use App\Lib\LanguagesLib;
use Cake\Event\EventManager;
use Cake\Form\Form;
use Cake\Form\Schema;
use Cake\Utility\Inflector;

class SentencesSearchForm extends Form
{
    use \Cake\Datasource\ModelAwareTrait;

    private $search;

    private $defaultCriteria = [
        'query' => '',
        'from' => 'und',
        'to' => 'und',
        'tags' => '',
        'list' => '',
        'user' => '',
        'orphans' => 'no',
        'unapproved' => 'no',
        'native' => '',
        'has_audio' => '',
        'trans_to' => 'und',
        'trans_link' => '',
        'trans_user' => '',
        'trans_orphan' => '',
        'trans_unapproved' => '',
        'trans_has_audio' => '',
        'trans_filter' => 'limit',
        'sort' => 'relevance',
        'sort_reverse' => '',
    ];

    public function __construct(EventManager $eventManager = null) {
        parent::__construct($eventManager);
        $this->search = new Search();
    }

    public function setSearch(Search $search) {
        $this->search = $search;
    }

    protected function parseYesNoEmpty($value) {
        if (is_null($value)) {
            return '';
        } else {
            return $value ? 'yes' : 'no';
        }
    }

    protected function parseBoolNull($value) {
        return $value == 'yes' ? true : ($value == 'no' ? false : null);
    }

    protected function setBoolFilter(string $method, string $value) {
        $value = $this->parseBoolNull($value);
        $value = $this->search->$method($value);
        return $this->parseYesNoEmpty($value);
    }

    protected function setDataQuery(string $query) {
        $query = str_replace(
            ['　', "\u{a0}"],
            ' ',
            $query
        );
        return $this->search->filterByQuery($query);
    }

    protected function setDataFrom(string $from) {
        return $this->search->filterByLanguage($from) ?? 'und';
    }

    protected function setDataUser(string $user) {
        if (!empty($user)) {
            $this->loadModel('Users');
            $result = $this->Users->findByUsername($user, ['fields' => ['id']])->first();
            if ($result) {
                $this->search->filterByOwnerId($result->id);
            } else {
                $user = '';
            }
        }
        return $user;
    }

    protected function setDataTransFilter(string $trans_filter) {
        /* If an invalid value was provided, fallback to 'limit' */
        if (!in_array($trans_filter, ['exclude', 'limit'])) {
            $trans_filter = 'limit';
        }

        /* Only set translation filter to 'limit' if at least
           one translation filter is set */
        if ($trans_filter == 'limit' && $this->search->getTranslationFilters()
            || $trans_filter == 'exclude') {
            $trans_filter = $this->search->filterByTranslation($trans_filter);
        }

        return $trans_filter;
    }

    protected function setDataTransLink(string $link) {
        return $this->search->filterByTranslationLink($link) ?? '';
    }

    protected function setDataTransUser(string $trans_user) {
        if (strlen($trans_user)) {
            $this->loadModel('Users');
            $result = $this->Users->findByUsername($trans_user, ['fields' => ['id']])->first();
            if ($result) {
                $this->search->filterByTranslationOwnerId($result->id);
            } else {
                $trans_user = '';
            }
        }
        return $trans_user;
    }

    protected function setDataTransTo(string $lang) {
        return $this->search->filterByTranslationLanguage($lang) ?? 'und';
    }

    protected function setDataTransHasAudio(string $trans_has_audio) {
        return $this->setBoolFilter('filterByTranslationAudio', $trans_has_audio);
    }

    protected function setDataTransUnapproved(string $trans_unapproved) {
        return $this->setBoolFilter('filterByTranslationCorrectness', $trans_unapproved);
    }

    protected function setDataTransOrphan(string $trans_orphan) {
        return $this->setBoolFilter('filterByTranslationOrphanship', $trans_orphan);
    }

    protected function setDataUnapproved(string $unapproved) {
        return $this->setBoolFilter('filterByCorrectness', $unapproved);
    }

    protected function setDataOrphans(string $orphans) {
        return $this->setBoolFilter('filterByOrphanship', $orphans);
    }

    protected function setDataHasAudio(string $has_audio) {
        return $this->setBoolFilter('filterByAudio', $has_audio);
    }

    protected function setDataTags(string $tags) {
        if (!empty($tags)) {
            $tagsArray = explode(',', $tags);
            $tagsArray = array_map('trim', $tagsArray);
            $appliedTags = $this->search->filterByTags($tagsArray);
            $tags = implode(',', $appliedTags);
        }
        return $tags;
    }

    protected function setDataList(string $list) {
        $searcher = CurrentUser::get('id');
        if (!$this->search->filterByListId($list, $searcher)) {
            $list = '';
        }
        return $list;
    }

    protected function setDataNative(string $native) {
        $native = $this->search->filterByNativeSpeaker($native === 'yes');
        return $native ? 'yes' : '';
    }

    protected function setDataSort(string $sort) {
        $sort = $this->search->sort($sort);

        /* If an invalid sort was provided,
           fallback to default sort instead of no sort */
        return $sort ?? $this->search->sort($this->defaultCriteria['sort']);
    }

    protected function setDataTo(string $to) {
        if ($to != 'none') {
            $to = LanguagesLib::languageExists($to) ? $to : 'und';
        }
        return $to;
    }

    protected function setDataSortReverse(string $sort_reverse) {
        $sort_reverse = $this->search->reverseSort($sort_reverse === 'yes');
        return $sort_reverse ? 'yes' : '';
    }

    public function setData(array $data)
    {
        /* Convert simple search to advanced search parameters */
        if (isset($data['to']) && !isset($data['trans_to'])) {
            $data['trans_to'] = $data['to'];
        }

        /* Apply default criteria */
        $data = array_merge($this->defaultCriteria, $data);

        /* Make sure trans_filter is applied at the end
           because it depends on other trans_* filters */
        uksort($data, function ($k) {
            return $k == 'trans_filter';
        });

        /* Apply given criteria */
        foreach ($data as $key => $value) {
            $keyCamel = Inflector::camelize($key);
            $setter = "setData$keyCamel";
            $this->_data[$key] = $this->$setter($value);
        }
    }
}
