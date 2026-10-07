<script setup>
import { Head, Link, router } from '@statamic/cms/inertia';
import { toast } from '@statamic/cms/api';
import { Badge, Button, Card, Header, PublishForm, TabContent, TabList, Tabs, TabTrigger, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';
import { computed, ref } from 'vue';
import ReportProgress from '../components/ReportProgress.vue';
import Score from '../components/Score.vue';
import When from '../components/When.vue';
import { useAxios } from '../util.js';

const props = defineProps({
    reports: { type: Array, required: true },
    canRun: { type: Boolean, required: true },
    runUrl: { type: String, required: true },
    // The Settings tab: null for whoever may not change the addon's settings.
    settings: { type: Object, default: null },
});

const axios = useAxios();
const starting = ref(false);
// Settings opens straight from a link to #settings.
const tab = ref(props.settings && window.location.hash === '#settings' ? 'settings' : 'reports');
const started = ref(null);
const running = computed(() => started.value ?? props.reports.find((report) => report.status === 'running'));

async function run() {
    starting.value = true;

    try {
        started.value = (await axios.post(props.runUrl)).data;
    } catch (error) {
        toast.error(error.response?.data?.message ?? __('marketing-toolkit::reports.cp.could_not_start'));
    } finally {
        starting.value = false;
    }
}

function finished(report) {
    started.value = null;
    report.status === 'done' ? router.visit(report.url) : router.reload();
}
</script>

<template>
    <Head :title="__('marketing-toolkit::reports.cp.title')" />

    <Header :title="__('marketing-toolkit::reports.cp.title')" icon="charts-donut-graph">
        <Button v-if="canRun && tab === 'reports'" :text="__('marketing-toolkit::reports.cp.run')" variant="primary" :loading="starting" :disabled="!!running" @click="run" />
    </Header>

    <Tabs v-model="tab">
        <TabList v-if="settings" class="mb-6">
            <TabTrigger name="reports" :text="__('marketing-toolkit::reports.cp.tab_reports')" />
            <TabTrigger name="settings" :text="__('marketing-toolkit::reports.cp.tab_settings')" />
        </TabList>

        <TabContent name="reports">
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                {{ __('marketing-toolkit::reports.cp.intro') }}
            </p>

            <Card v-if="running" class="mb-6 p-4">
                <ReportProgress :key="running.id" :report="running" @done="finished" />
            </Card>

            <Card v-if="reports.length">
                <Table class="overflow-x-auto">
                    <TableColumns>
                        <TableColumn>{{ __('marketing-toolkit::reports.cp.column_report') }}</TableColumn>
                        <TableColumn>{{ __('marketing-toolkit::reports.cp.score') }}</TableColumn>
                        <TableColumn>{{ __('marketing-toolkit::reports.cp.pages') }}</TableColumn>
                        <TableColumn>{{ __('marketing-toolkit::reports.cp.finished') }}</TableColumn>
                    </TableColumns>
                    <TableRows>
                        <TableRow v-for="report in reports" :key="report.id">
                            <TableCell>
                                <Link v-if="report.status === 'done'" :href="report.url" class="font-medium">{{ __('marketing-toolkit::reports.cp.report', { id: report.id }) }}</Link>
                                <span v-else>{{ __('marketing-toolkit::reports.cp.report', { id: report.id }) }}</span>
                            </TableCell>
                            <TableCell>
                                <Score v-if="report.status === 'done'" :value="report.score" />
                                <Badge v-else-if="report.status === 'running'" :text="__('marketing-toolkit::reports.cp.running')" />
                                <Badge v-else color="red" :text="__('marketing-toolkit::reports.cp.failed')" :title="report.error" />
                            </TableCell>
                            <TableCell class="tabular-nums">{{ report.pages_total }}</TableCell>
                            <TableCell><When :value="report.finished_at" /></TableCell>
                        </TableRow>
                    </TableRows>
                </Table>
            </Card>
            <p v-else-if="!running" class="text-sm text-gray-600 dark:text-gray-400">{{ __('marketing-toolkit::reports.cp.none') }}<template v-if="canRun"> {{ __('marketing-toolkit::reports.cp.run_first') }}</template></p>
        </TabContent>

        <TabContent v-if="settings" name="settings">
            <PublishForm
                :title="__('marketing-toolkit::reports.cp.tab_settings')"
                :blueprint="settings.blueprint"
                :initial-values="settings.values"
                :initial-meta="settings.meta"
                :submit-url="settings.submitUrl"
                submit-method="post"
            />
        </TabContent>
    </Tabs>
</template>
