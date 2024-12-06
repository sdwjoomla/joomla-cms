<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_scheduler
 *
 * @copyright   (C) 2024 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Scheduler\Administrator\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Component\Scheduler\Administrator\Helper\SchedulerHelper;
use Joomla\Database\ParameterType;

/**
 * Supporting a list of logs.
 *
 * @since  __DEPLOY_VERSION__
 */
class LogsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array                $config   An optional associative array of configuration settings.
     * @param   MVCFactoryInterface  $factory  The factory.
     *
     * @see     \JControllerLegacy
     * @since   __DEPLOY_VERSION__
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'exitcode', 'a.exitcode',
                'duration', 'a.duration',
                'taskname', 'a.taskname',
                'type', 'a.tasktype',
                'jobid', 'a.jobid',
                'taskid', 'a.taskid',
                'lastdate', 'a.lastdate',
                'nextdate', 'a.nextdate',
            ];
        }

        parent::__construct($config, $factory);
    }

    /**
     * Removes all the logs from the table.
     *
     * @return  boolean result of operation
     *
     * @since   __DEPLOY_VERSION__
     */
    public function purge()
    {
        try {
            $this->getDatabase()->truncateTable('#__scheduler_logs');
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    protected function populateState($ordering = 'a.id', $direction = 'desc')
    {
        // Load the parameters.
        $params = ComponentHelper::getParams('com_scheduler');
        $this->setState('params', $params);

        // List state information.
        parent::populateState($ordering, $direction);
    }

    /**
     * Get a store id based on model configuration state.
     *
     * This is necessary because the model is used by the component and
     * different modules that might need different sets of data or different
     * ordering requirements.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since   __DEPLOY_VERSION__
     */
    protected function getStoreId($id = '')
    {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.state');
        $id .= ':' . $this->getState('filter.exitcode');
        $id .= ':' . $this->getState('filter.type');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \JDatabaseQuery
     *
     * @since   __DEPLOY_VERSION__
     */
    protected function getListQuery()
    {
        // Create a new query object.
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
            $this->getState(
                'list.select',
                'a.*'
            )
        );
        $query->from($db->quoteName('#__scheduler_logs', 'a'));

        // Filter the items over the exit code.
        $exitCode = $this->getState('filter.exitcode');

        if (is_numeric($exitCode)) {
            $exitCode = (int) $exitCode;
            if ($exitCode >= 0) {
                $query->where($db->quoteName('a.exitcode') . ' = :exitcode')
                    ->bind(':exitcode', $exitCode, ParameterType::INTEGER);
            } else {
                $query->whereNotIn($db->quoteName('a.exitcode'), [0, 123], ParameterType::INTEGER);
            }
        }

        // Filter the items over the search string if set.
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $ids = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :id');
                $query->bind(':id', $ids, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', $db->escape(trim($search), true) . '%');
                $query->where($db->quoteName('taskname') . ' LIKE :taskname')
                    ->bind(':taskname', $search);
            }
        }

        // Filter over type
        $typeFilter = $this->getState('filter.type');

        if ($typeFilter) {
            $taskOptions   = SchedulerHelper::getTaskOptions();
            $safeTypeTitle = $taskOptions->findOption($typeFilter)->title ?? '';
            $query->where($db->quotename('a.tasktype') . ' = :type')
                ->bind(':type', $safeTypeTitle);
        }

        // Add the list ordering clause.
        $query->order($db->escape($this->getState('list.ordering', 'a.lastdate')) . ' ' . $db->escape($this->getState('list.direction', 'DESC')));

        return $query;
    }

    /**
     * Delete rows.
     *
     * @param   array    $pks    The ids of the items to delete.
     *
     * @return  boolean  Returns true on success, false on failure.
     */
    public function delete($pks)
    {
        if ($this->canDelete($pks)) {
            // Delete logs from list
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__scheduler_logs'))
                ->whereIn($db->quoteName('id'), $pks);

            $db->setQuery($query);
            $this->setError((string) $query);

            try {
                $db->execute();
            } catch (\RuntimeException $e) {
                $this->setError($e->getMessage());

                return false;
            }
        } else {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_CORE_DELETE_NOT_PERMITTED'), 'error');
        }

        return true;
    }

    /**
     * Determine whether a record may be deleted taking into consideration
     * the user's permissions over the record.
     *
     * @param   object  $record  The database row/record in question
     *
     * @return  boolean  True if the record may be deleted
     *
     * @since  __DEPLOY_VERSION__
     * @throws \Exception
     */
    protected function canDelete($record): bool
    {
        // Record doesn't exist, can't delete
        if (empty($record)) {
            return false;
        }

        return Factory::getApplication()->getIdentity()->authorise('core.delete', 'com_scheduler');
    }

    /**
     * @param   array   $data        The task execution data.
     *
     * @return void
     *
     * @since __DEPLOY_VERSION__
     * @throws Exception
     */
    public function logTask(array $data): void
    {
        $model         = Factory::getApplication()->bootComponent('com_scheduler')
            ->getMVCFactory()->createModel('Task', 'Administrator', ['ignore_request' => true]);
        $taskInfo      = $model->getItem($data['TASK_ID']);
        $taskOptions   = SchedulerHelper::getTaskOptions();
        $safeTypeTitle = $taskOptions->findOption($taskInfo->type)->title ?? '';
        $duration      = ($data['TASK_DURATION'] ?? 0);
        $created       = Factory::getDate()->toSql();

        /** @var \Joomla\Component\Schduler\Administrator\Table\LogsTable $table */
        $logsTable           = $this->getTable();
        $logsTable->tasktype = $safeTypeTitle;
        $logsTable->taskname = $data['TASK_TITLE'];
        $logsTable->duration = $duration;
        $logsTable->jobid    = $data['TASK_ID'];
        $logsTable->exitcode = $data['EXIT_CODE'];
        $logsTable->taskid   = $data['TASK_TIMES'];
        $logsTable->lastdate = $created;
        $logsTable->nextdate = $taskInfo->next_execution;

        // Log the execution of the task.
        $logsTable->store();
    }
}
