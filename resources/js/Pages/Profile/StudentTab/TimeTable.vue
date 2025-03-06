<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Avatar, Card, Select } from 'primevue';
import { ref } from 'vue';

const schedules = ref([
  {
    day: "Monday",
    sessions: [
      {
        time: "09:00 - 09:45 AM",
        subject: "Maths",
        teacherName: "Jacquelin",
        teacherImage: "assets/img/teachers/teacher-07.jpg"
      },
      {
        time: "09:45 - 10:30 AM",
        subject: "English",
        teacherName: "Hellana",
        teacherImage: "assets/img/teachers/teacher-03.jpg"
      },
      {
        time: "10:45 - 11:30 AM",
        subject: "Computer",
        teacherName: "Daniel",
        teacherImage: "assets/img/teachers/teacher-02.jpg"
      },
      {
        time: "11:30 - 12:15 PM",
        subject: "Spanish",
        teacherName: "Erickson",
        teacherImage: "assets/img/teachers/teacher-03.jpg"
      },
      {
        time: "01:30 - 02:15 PM",
        subject: "Science",
        teacherName: "Morgan",
        teacherImage: "assets/img/teachers/teacher-05.jpg"
      },
      {
        time: "02:15 - 03:00 PM",
        subject: "Chemistry",
        teacherName: "Aaron",
        teacherImage: "assets/img/teachers/teacher-06.jpg"
      },
      {
        time: "03:15 - 04:00 PM",
        subject: "Physics",
        teacherName: "Teresa",
        teacherImage: "assets/img/teachers/teacher-01.jpg"
      }
    ]
  },
  {
    day: "Tuesday",
    sessions: [
      {
        time: "09:00 - 09:45 AM",
        subject: "Spanish",
        teacherName: "Erickson",
        teacherImage: "assets/img/teachers/teacher-03.jpg"
      },
      {
        time: "09:45 - 10:30 AM",
        subject: "Physics",
        teacherName: "Teresa",
        teacherImage: "assets/img/teachers/teacher-01.jpg"
      },
      {
        time: "10:45 - 11:30 AM",
        subject: "Chemistry",
        teacherName: "Aaron",
        teacherImage: "assets/img/teachers/teacher-06.jpg"
      },
      {
        time: "11:30 - 12:15 PM",
        subject: "Maths",
        teacherName: "Jacquelin",
        teacherImage: "assets/img/teachers/teacher-07.jpg"
      },
      {
        time: "01:30 - 02:15 PM",
        subject: "Computer",
        teacherName: "Daniel",
        teacherImage: "assets/img/teachers/teacher-02.jpg"
      },
      {
        time: "02:15 - 03:00 PM",
        subject: "English",
        teacherName: "Hellana",
        teacherImage: "assets/img/teachers/teacher-03.jpg"
      },
      {
        time: "03:15 - 04:00 PM",
        subject: "Science",
        teacherName: "Morgan",
        teacherImage: "assets/img/teachers/teacher-05.jpg"
      }
    ]
  },
  {
    day: "Wednesday",
    sessions: [
      {
        time: "09:00 - 09:45 AM",
        subject: "Computer",
        teacherName: "Daniel",
        teacherImage: "assets/img/teachers/teacher-02.jpg"
      },
      {
        time: "09:45 - 10:30 AM",
        subject: "Science",
        teacherName: "Morgan",
        teacherImage: "assets/img/teachers/teacher-05.jpg"
      },
      {
        time: "10:45 - 11:30 AM",
        subject: "Maths",
        teacherName: "Jacquelin",
        teacherImage: "assets/img/teachers/teacher-07.jpg"
      },
      {
        time: "11:30 - 12:15 PM",
        subject: "Chemistry",
        teacherName: "Aaron",
        teacherImage: "assets/img/teachers/teacher-06.jpg"
      },
      {
        time: "01:30 - 02:15 PM",
        subject: "Physics",
        teacherName: "Teresa",
        teacherImage: "assets/img/teachers/teacher-01.jpg"
      },
      {
        time: "02:15 - 03:00 PM",
        subject: "English",
        teacherName: "Hellana",
        teacherImage: "assets/img/teachers/teacher-03.jpg"
      },
      {
        time: "03:15 - 04:00 PM",
        subject: "Spanish",
        teacherName: "Erickson",
        teacherImage: "assets/img/teachers/teacher-03.jpg"
      }
    ]
  },
  {
    day: "Thursday",
    sessions: [
      {
        time: "09:00 - 09:45 AM",
        subject: "Physics",
        teacherName: "Teresa",
        teacherImage: "assets/img/teachers/teacher-01.jpg"
      },
      {
        time: "09:45 - 10:30 AM",
        subject: "Computer",
        teacherName: "Daniel",
        teacherImage: "assets/img/teachers/teacher-02.jpg"
      },
      {
        time: "10:45 - 11:30 AM",
        subject: "English",
        teacherName: "Hellana",
        teacherImage: "assets/img/teachers/teacher-03.jpg"
      },
      {
        time: "11:30 - 12:15 PM",
        subject: "Science",
        teacherName: "Morgan",
        teacherImage: "assets/img/teachers/teacher-05.jpg"
      },
      {
        time: "01:30 - 02:15 PM",
        subject: "Spanish",
        teacherName: "Erickson",
        teacherImage: "assets/img/teachers/teacher-03.jpg"
      },
      {
        time: "02:15 - 03:00 PM",
        subject: "Chemistry",
        teacherName: "Aaron",
        teacherImage: "assets/img/teachers/teacher-06.jpg"
      },
      {
        time: "03:15 - 04:00 PM",
        subject: "Maths",
        teacherName: "Jacquelin",
        teacherImage: "assets/img/teachers/teacher-07.jpg"
      }
    ]
  },
  {
    day: "Friday",
    sessions: [
      {
        time: "09:00 - 09:45 AM",
        subject: "English",
        teacherName: "Hellana",
        teacherImage: "assets/img/teachers/teacher-03.jpg"
      },
      {
        time: "09:45 - 10:30 AM",
        subject: "Spanish",
        teacherName: "Erickson",
        teacherImage: "assets/img/teachers/teacher-03.jpg"
      },
      {
        time: "10:45 - 11:30 AM",
        subject: "Physics",
        teacherName: "Teresa",
        teacherImage: "assets/img/teachers/teacher-01.jpg"
      },
      {
        time: "11:30 - 12:15 PM",
        subject: "Chemistry",
        teacherName: "Aaron",
        teacherImage: "assets/img/teachers/teacher-06.jpg"
      },
      {
        time: "01:30 - 02:15 PM",
        subject: "Maths",
        teacherName: "Jacquelin",
        teacherImage: "assets/img/teachers/teacher-07.jpg"
      },
      {
        time: "02:15 - 03:00 PM",
        subject: "Computer",
        teacherName: "Daniel",
        teacherImage: "assets/img/teachers/teacher-02.jpg"
      },
      {
        time: "03:15 - 04:00 PM",
        subject: "Science",
        teacherName: "Morgan",
        teacherImage: "assets/img/teachers/teacher-05.jpg"
      }
    ]
  },
  {
    day: "Saturday",
    sessions: [
      {
        time: "09:00 - 09:45 AM",
        subject: "English",
        teacherName: "Hellana",
        teacherImage: "assets/img/teachers/teacher-03.jpg"
      },
      {
        time: "09:45 - 10:30 AM",
        subject: "Spanish",
        teacherName: "Erickson",
        teacherImage: "assets/img/teachers/teacher-03.jpg"
      },
      {
        time: "10:45 - 11:30 AM",
        subject: "Physics",
        teacherName: "Teresa",
        teacherImage: "assets/img/teachers/teacher-01.jpg"
      },
      {
        time: "11:30 - 12:15 PM",
        subject: "Chemistry",
        teacherName: "Aaron",
        teacherImage: "assets/img/teachers/teacher-06.jpg"
      },
      {
        time: "01:30 - 02:15 PM",
        subject: "Maths",
        teacherName: "Jacquelin",
        teacherImage: "assets/img/teachers/teacher-07.jpg"
      },
      {
        time: "02:15 - 03:00 PM",
        subject: "Computer",
        teacherName: "Daniel",
        teacherImage: "assets/img/teachers/teacher-02.jpg"
      },
      {
        time: "03:15 - 04:00 PM",
        subject: "Science",
        teacherName: "Morgan",
        teacherImage: "assets/img/teachers/teacher-05.jpg"
      }
    ]
  }
]);

const color = ref(['bg-primary-200/30', 'bg-red-200/30', 'bg-yellow-200/30', 'bg-sky-200/30', 'bg-green-200/30']);
</script>

<template>

    <Card class="card">
        <div class="card-footer border-0 pb-0">
            <div class="row">
                <div class="col-lg-4 col-xxl-4 col-xl-4 flex">
                    <div class="card flex-1">
                        <div class="card-body">
                            <span class="bg-primary badge badge-sm mb-2">Morning Break</span>
                            <p class="text-dark"><i class="ti ti-clock mr-1"></i>10:30 to 10 :45 AM</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-xxl-3 flex">
                    <div class="card flex-1">
                        <div class="card-body">
                            <span class="bg-warning badge badge-sm mb-2">Lunch</span>
                            <p class="text-dark"><i class="ti ti-clock mr-1"></i>10:30 to 10 :45 AM</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-xxl-3 flex">
                    <div class="card flex-1">
                        <div class="card-body">
                            <span class="bg-info badge badge-sm mb-2">Evening Break</span>
                            <p class="text-dark"><i class="ti ti-clock mr-1"></i>03:30 PM to 03:45 PM</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <template #footer></template>
        <template #content>
            <div class="flex flex-nowrap overflow-auto">
                <div v-for="{day, sessions} in schedules" class="flex flex-col mr-4 flex-1">
                    <div class="mb-3">
                        <h6>{{ day }}</h6>
                    </div>
                    <div v-for="{ time, subject, teacherName, teacherImage} in sessions" :class="color[Math.floor(Math.random() * 5)]" class="rounded p-3 mb-4">
                        <p class="flex items-center text-nowrap mb-1">
                            <i class="ti ti-clock mr-1"></i>{{ time }}</p>
                        <p class="text-dark">Subject : {{ subject }}</p>
                        <div class="bg-surface-0 rounded p-1 mt-3">
                            <Link href="teacher-details.html" class="text-muted flex items-center gap-x-2">
                                <Avatar :image="teacherImage" />
                                {{ teacherName }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </template>
        <template #header>
            <div class="flex items-center justify-between flex-wrap p-4 pb-0 border-b mb-2">
                <h4 class="p-card-title">Exams & Results</h4>
                <Select :options="['This Year', 'Last Year']" model-value="This Year" />
            </div>
        </template>
    </Card>
</template>
