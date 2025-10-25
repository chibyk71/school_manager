<!-- TODO npm install vue-chartjs chart.js -->
<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Financial Reporting Dashboard</h1>
    <Toast />

    <!-- Filters -->
    <div class="card p-4 mb-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block mb-1">Start Date</label>
          <InputText v-model="filters.start_date" type="date" class="w-full" />
        </div>
        <div>
          <label class="block mb-1">End Date</label>
          <InputText v-model="filters.end_date" type="date" class="w-full" />
        </div>
        <div>
          <label class="block mb-1">Fee Type</label>
          <Dropdown v-model="filters.fee_type_id" :options="feeTypes" optionLabel="name" optionValue="id" placeholder="Select Fee Type" class="w-full" />
        </div>
        <div>
          <label class="block mb-1">Payment Status</label>
          <Dropdown v-model="filters.payment_status" :options="['pending', 'success', 'failed']" placeholder="Select Status" class="w-full" />
        </div>
      </div>
      <Button label="Apply Filters" class="mt-4" @click="applyFilters" />
      <Button label="Export CSV" class="mt-4 ml-2" @click="exportReport" />
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
      <div class="card p-4">
        <h2 class="text-lg font-semibold">Total Fees</h2>
        <p class="text-2xl">{{ formatCurrency(total_fees) }}</p>
      </div>
      <div class="card p-4">
        <h2 class="text-lg font-semibold">Total Payments</h2>
        <p class="text-2xl">{{ formatCurrency(total_payments) }}</p>
      </div>
      <div class="card p-4">
        <h2 class="text-lg font-semibold">Outstanding Balances</h2>
        <p class="text-2xl">{{ formatCurrency(outstanding_balances) }}</p>
      </div>
      <div class="card p-4">
        <h2 class="text-lg font-semibold">Total Expenses</h2>
        <p class="text-2xl">{{ formatCurrency(total_expenses) }}</p>
      </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div class="card p-4">
        <h2 class="text-lg font-semibold mb-2">Payments by Month</h2>
        <BarChart :chartData="paymentsByMonthData" :options="chartOptions" />
      </div>
      <div class="card p-4">
        <h2 class="text-lg font-semibold mb-2">Expenses by Category</h2>
        <PieChart :chartData="expensesByCategoryData" :options="chartOptions" />
      </div>
    </div>

    <!-- Detailed Payments Table -->
    <div class="card p-4">
      <h2 class="text-lg font-semibold mb-2">Detailed Payments</h2>
      <DataTable :value="detailed_payments.data" :filters="filters" :paginator="true" :rows="filters.perPage">
        <Column field="student_name" header="Student" sortable filter></Column>
        <Column field="fee_name" header="Fee" sortable filter></Column>
        <Column field="feeInstallmentDetail.amount" header="Installment Amount" sortable></Column>
        <Column field="payment_amount" header="Payment Amount" sortable></Column>
        <Column field="payment_currency" header="Currency" sortable></Column>
        <Column field="payment_status" header="Status" sortable filter></Column>
        <Column field="payment_reference" header="Reference" sortable></Column>
        <Column field="payment_date" header="Date" sortable></Column>
        <Column header="Actions">
          <template #body="{ data }">
            <Button v-if="data.deleted_at" label="Restore" @click="restore(data.id)" />
            <Button v-if="data.deleted_at" label="Force Delete" @click="delete([data.id], true)" />
            <Button v-else label="Delete" @click="delete([data.id])" />
          </template>
        </Column>
      </DataTable>
    </div>
  </div>
</template>

<script>
import { Bar, Pie } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement } from 'chart.js';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Toast from 'primevue/toast';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement);

export default {
  components: { BarChart: Bar, PieChart: Pie, InputText, Dropdown, Button, DataTable, Column, Toast },
  props: {
    total_fees: Number,
    fees_by_type: Array,
    total_payments: Number,
    payments_by_month: Array,
    payments_by_method: Array,
    outstanding_balances: Number,
    overdue_balances: Number,
    total_expenses: Number,
    expenses_by_category: Array,
    detailed_payments: Object,
    filters: Object,
    feeTypes: Array,
  },
  data() {
    return {
      chartOptions: {
        responsive: true,
        maintainAspectRatio: false,
      },
      localFilters: { ...this.filters },
    };
  },
  computed: {
    paymentsByMonthData() {
      return {
        labels: this.payments_by_month.map(item => item.month),
        datasets: [
          {
            label: 'Payments by Month',
            backgroundColor: '#42A5F5',
            data: this.payments_by_month.map(item => item.total),
          },
        ],
      };
    },
    expensesByCategoryData() {
      return {
        labels: this.expenses_by_category.map(item => item.category),
        datasets: [
          {
            label: 'Expenses by Category',
            backgroundColor: ['#42A5F5', '#66BB6A', '#FFCA28', '#EF5350', '#AB47BC'],
            data: this.expenses_by_category.map(item => item.total),
          },
        ],
      };
    },
  },
  methods: {
    formatCurrency(value) {
      return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(value);
    },
    applyFilters() {
      this.$inertia.get('/finance/reports', this.localFilters, { preserveState: true });
    },
    exportReport() {
      window.location.href = '/finance/reports/export?' + new URLSearchParams(this.localFilters).toString();
    },
    delete(ids, force = false) {
      this.$inertia.delete('/finance/payments', { data: { ids, force } });
    },
    restore(id) {
      this.$inertia.post(`/finance/payments/${id}/restore`);
    },
  },
};
</script>

<style scoped>
.card {
  @apply bg-white shadow-md rounded-lg p-4;
}
</style>