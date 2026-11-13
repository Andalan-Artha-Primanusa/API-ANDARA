import { createBrowserRouter, Navigate, Outlet } from "react-router-dom";
import { lazy } from "react";
const NotFoundPage = lazy(() => import("../../pages/error/NotFoundPage"));
import { getRoleBasedDashboardPathFromStorage } from "@/features/auth/utils/roleRedirect";
import { ProtectedRoute } from "./ProtectedRoute";
import { MenuRouteGuard } from "./MenuRouteGuard";
import { useAuthSession } from "./useAuthSession";
import LoginPage from "../../pages/auth/login/LoginPage";
import GoogleCallbackPage from "../../pages/auth/login/GoogleCallbackPage";
import RegisterPage from "../../pages/auth/register/RegisterPage";
import ForgotPasswordPage from "../../pages/auth/login/ForgotPasswordPage";
import ResetPasswordPage from "../../pages/auth/login/ResetPasswordPage";
import DashboardLayout from "../layouts/DashboardLayout";
const OverviewPage = lazy(() => import("../../pages/dashboard/overview/OverviewPage"));
const AttendanceOverviewPage = lazy(() => import("../../pages/attendance/AttendanceOverviewPage"));
const AttendanceCheckInPage = lazy(() => import("../../pages/attendance/AttendanceCheckInPage"));
const AttendanceCheckOutPage = lazy(() => import("../../pages/attendance/AttendanceCheckOutPage"));
const AttendanceHistoryPage = lazy(() => import("../../pages/attendance/AttendanceHistoryPage"));
const AttendanceTodayPage = lazy(() => import("../../pages/attendance/AttendanceTodayPage"));
const OvertimePage = lazy(() => import("../../pages/attendance/OvertimePage"));
const AttendanceReportsPage = lazy(() => import("../../pages/attendance/AttendanceReportsPage"));
const KpiListPage = lazy(() => import("../../pages/admin/AdminKpiPage"));
const KpiFormPage = lazy(() => import("../../pages/admin/KpiFormPage"));
const MyKpiPage = lazy(() => import("../../pages/ess/MyKpiPage"));
const SectionPage = lazy(() => import("../../pages/dashboard/SectionPage"));
const ProfilesPage = lazy(() => import("../../pages/profiles/ProfilesPage"));
const EmployeesPage = lazy(() => import("../../pages/employee/EmployeesPage"));
const EmployeeCreatePage = lazy(() => import("../../pages/employee/EmployeeCreatePage"));
const EmployeeEditPage = lazy(() => import("../../pages/employee/EmployeeEditPage"));
const MyReimbursementsPage = lazy(() => import("../../pages/ess/MyReimbursementsPage"));
const AdminReimbursementsPage = lazy(() => import("../../pages/admin/AdminReimbursementsPage"));
const MyPayrollPage = lazy(() => import("../../pages/ess/MyPayrollPage"));
const MyLeavesPage = lazy(() => import("../../pages/ess/MyLeavesPage"));
const LeaveBalancePage = lazy(() => import("../../pages/leave/LeaveBalancePage"));
const EmployeeDashboardPage = lazy(() => import("../../pages/dashboard/EmployeeDashboardPage"));
const MyTrainingsPage = lazy(() => import("../../pages/ess/MyTrainingsPage"));
const MyCompetenciesPage = lazy(() => import("../../pages/ess/MyCompetenciesPage"));
const MyDocumentsPage = lazy(() => import("../../pages/ess/MyDocumentsPage"));
const LocationsPage = lazy(() => import("../../pages/locations/LocationsPage"));
const CreateLocationPage = lazy(() => import("../../pages/locations/CreateLocationPage"));
const EditLocationPage = lazy(() => import("../../pages/locations/EditLocationPage"));
const AdminUsersPage = lazy(() => import("../../pages/admin/AdminUsersPage"));
const AdminRolesPage = lazy(() => import("../../pages/admin/AdminRolesPage"));
const AdminPermissionsPage = lazy(() => import("../../pages/admin/AdminPermissionsPage"));
const MenuPermissionsPage = lazy(() => import("../../pages/admin/MenuPermissionsPage"));
const AdminUserAssignRolesPage = lazy(() => import("../../pages/admin/AdminUserAssignRolesPage"));
const AdminRoleAssignPermissionsPage = lazy(() => import("../../pages/admin/AdminRoleAssignPermissionsPage"));
const AdminNotificationsPage = lazy(() => import("../../pages/admin/AdminNotificationsPage"));
const AdminEmailSendPage = lazy(() => import("../../pages/admin/AdminEmailSendPage"));
const AdminEmailNotificationsPage = lazy(() => import("../../pages/admin/AdminEmailNotificationsPage"));
const AdminAuditLogsPage = lazy(() => import("../../pages/admin/AdminAuditLogsPage"));
const AdminImportPage = lazy(() => import("../../pages/admin/AdminImportPage"));
const AdminRoleFormPage = lazy(() => import("../../pages/admin/AdminRoleFormPage"));
const AdminBiometricDevicesPage = lazy(() => import("../../pages/admin/AdminBiometricDevicesPage"));
const LeaveRequestsPage = lazy(() => import("../../pages/leave/LeaveRequestsPage"));
const CreateLeavePage = lazy(() => import("../../pages/leave/CreateLeavePage"));
const UpdateLeavePage = lazy(() => import("../../pages/leave/UpdateLeavePage"));
const LeaveCalendarPage = lazy(() => import("../../pages/leave/LeaveCalendarPage"));
const LeaveApprovalPage = lazy(() => import("../../pages/leave/LeaveApprovalPage"));
const PayrollManagementPage = lazy(() => import("../../pages/payroll/PayrollManagementPage"));
const PayrollDetailsPage = lazy(() => import("../../pages/payroll/PayrollDetailsPage"));
const PayrollListPage = lazy(() => import("../../pages/payroll/PayrollListPage"));
const PayrollProcessPage = lazy(() => import("../../pages/payroll/PayrollProcessPage"));
const PayrollReportsPage = lazy(() => import("../../pages/payroll/PayrollReportsPage"));
const PayrollDashboard = lazy(() => import("../../pages/payroll/PayrollDashboard"));
const NotificationsPage = lazy(() => import("../../pages/notifications/NotificationsPage"));
const ReportsDashboardPage = lazy(() => import("../../pages/reports/ReportsDashboardPage"));
const ReportsAttendancePage = lazy(() => import("../../pages/reports/ReportsAttendancePage"));
const ReportsLeavePage = lazy(() => import("../../pages/reports/ReportsLeavePage"));
const ReportsPayrollPage = lazy(() => import("../../pages/reports/ReportsPayrollPage"));

const ReportsAssetsPage = lazy(() => import("../../pages/reports/ReportsAssetsPage"));
const ReportsEmployeePage = lazy(() => import("../../pages/reports/ReportsEmployeePage"));
const WorkSchedulesPage = lazy(() => import("../../pages/work-schedule/WorkSchedulesPage"));
const WorkScheduleCreatePage = lazy(() => import("../../pages/work-schedule/WorkScheduleCreatePage"));
const WorkScheduleEditPage = lazy(() => import("../../pages/work-schedule/WorkScheduleEditPage"));
const JobOpeningsPage = lazy(() => import("../../pages/admin/JobOpeningsPage"));
const CandidatePipelinePage = lazy(() => import("../../pages/admin/CandidatePipelinePage"));
const TalentPoolPage = lazy(() => import("../../pages/admin/TalentPoolPage"));
const OkrManagementPage = lazy(() => import("../../pages/admin/OkrManagementPage"));
const EngagementSurveysPage = lazy(() => import("../../pages/admin/EngagementSurveysPage"));
const EngagementAnalyticsPage = lazy(() => import("../../pages/admin/EngagementAnalyticsPage"));
const SeveranceCalculatorPage = lazy(() => import("../../pages/admin/SeveranceCalculatorPage"));
const EmploymentLettersPage = lazy(() => import("../../pages/admin/EmploymentLettersPage"));
const OrgChartPage = lazy(() => import("../../pages/admin/OrgChartPage"));
const HrRequestsPage = lazy(() => import("../../pages/admin/HrRequestsPage"));
const ComplianceDashboardPage = lazy(() => import("../../pages/admin/ComplianceDashboardPage"));
const HolidayCalendarPage = lazy(() => import("../../pages/admin/HolidayCalendarPage"));
const TrainingManagementPage = lazy(() => import("../../pages/admin/TrainingManagementPage"));
const CompetencyMatrixPage = lazy(() => import("../../pages/admin/CompetencyMatrixPage"));
const ApprovalFlowPage = lazy(() => import("../../pages/admin/ApprovalFlowPage"));
const ProgressiveTaxPage = lazy(() => import("../../pages/admin/ProgressiveTaxPage"));
const SuccessionMatrixPage = lazy(() => import("../../pages/admin/SuccessionMatrixPage"));
const IdpPage = lazy(() => import("../../pages/admin/IdpPage"));
const CalibrationPage = lazy(() => import("../../pages/admin/CalibrationPage"));
const Review360Page = lazy(() => import("../../pages/admin/Review360Page"));
const ShiftSwapsPage = lazy(() => import("../../pages/admin/ShiftSwapsPage"));
const OvertimeRulesPage = lazy(() => import("../../pages/admin/OvertimeRulesPage"));
const BiometricDevicesPage = lazy(() => import("../../pages/admin/BiometricDevicesPage"));
const CompensationPage = lazy(() => import("../../pages/admin/CompensationPage"));
const ComplianceSettingsPage = lazy(() => import("../../pages/admin/ComplianceSettingsPage"));
const AssetManagementPage = lazy(() => import("../../pages/admin/AssetManagementPage"));
const AssetFormPage = lazy(() => import("../../pages/admin/AssetFormPage"));
const AssignmentLettersPage = lazy(() => import("../../pages/admin/AssignmentLettersPage.tsx"));
const TaskManagementPage = lazy(() => import("../../pages/admin/TaskManagementPage.tsx"));
const PromotionPage = lazy(() => import("../../pages/admin/PromotionPage.tsx"));

const MyAssetsPage = lazy(() => import("../../pages/ess/MyAssetsPage.tsx"));
const MyAssignmentLettersPage = lazy(() => import("../../pages/ess/MyAssignmentLettersPage.tsx"));
const MyTasksPage = lazy(() => import("../../pages/ess/MyTasksPage.tsx"));
const MyPromotionsPage = lazy(() => import("../../pages/ess/MyPromotionsPage.tsx"));
// const MyDocumentsPage = lazy(() => import("../../pages/ess/MyDocumentsPage.tsx"));
const ShiftSwapFormPage = lazy(() => import("../../pages/admin/ShiftSwapFormPage.tsx"));
const LeavePolicyPage = lazy(() => import("../../pages/admin/LeavePolicyPage"));
const LeavePolicyFormPage = lazy(() => import("../../pages/admin/LeavePolicyFormPage"));
const LeaveTypePage = lazy(() => import("../../pages/admin/LeaveTypePage"));
const LeaveTypeFormPage = lazy(() => import("../../pages/admin/LeaveTypeFormPage"));
const BiometricDeviceFormPage = lazy(() => import("../../pages/admin/BiometricDeviceFormPage.tsx"));
const JobOpeningFormPage = lazy(() => import("../../pages/admin/JobOpeningFormPage.tsx"));
const OkrFormPage = lazy(() => import("../../pages/admin/OkrFormPage.tsx"));
const SurveyFormPage = lazy(() => import("../../pages/admin/SurveyFormPage.tsx"));
const HrRequestFormPage = lazy(() => import("../../pages/admin/HrRequestFormPage.tsx"));
const SlaPage = lazy(() => import("../../pages/admin/SlaPage.tsx"));
const HolidayFormPage = lazy(() => import("../../pages/admin/HolidayFormPage.tsx"));
const TrainingFormPage = lazy(() => import("../../pages/admin/TrainingFormPage.tsx"));
const CalibrationFormPage = lazy(() => import("../../pages/admin/CalibrationFormPage.tsx"));
const OvertimeRuleFormPage = lazy(() => import("../../pages/admin/OvertimeRuleFormPage.tsx"));
const BenefitManagementPage = lazy(() => import("../../pages/admin/BenefitManagementPage.tsx"));
const BenefitFormPage = lazy(() => import("../../pages/admin/BenefitFormPage.tsx"));
const DetailedPeopleAnalyticsPage = lazy(() => import("../../pages/admin/DetailedPeopleAnalyticsPage.tsx"));
const NotificationRulesPage = lazy(() => import("../../pages/admin/NotificationRulesPage.tsx"));
const CompanySettingsPage = lazy(() => import("../../pages/admin/CompanySettingsPage.tsx"));
const NotificationSettingsPage = lazy(() => import("../../pages/admin/NotificationSettingsPage.tsx"));
const MasterDataPage = lazy(() => import("../../pages/admin/MasterDataPage.tsx"));


























const sectionRoutes = [
  { path: "/hr-summary" },
  { path: "/analytics" },
  { path: "/insights/people/detailed" },
  { path: "/organization/directory" },
  { path: "/organization/summary" },
  { path: "/organization/chart" },
  { path: "/organization/team" },
  { path: "/documents/review" },
  { path: "/documents/expiring" },
  { path: "/notifications" },
  { path: "/attendance/timesheet" },
  { path: "/attendance/overtime" },
  { path: "/attendance/reports" },
  { path: "/my/assets" },
  { path: "/my/trainings" },
  { path: "/my/competencies" },
  { path: "/my/requests" },
  { path: "/requests" },
  { path: "/requests/assign" },
  { path: "/requests/status" },
  { path: "/compliance/audit-summary" },
  { path: "/compliance/expiring-documents" },
  { path: "/expense/categories" },
  { path: "/expense/reports" },
  { path: "/performance" },
  { path: "/performance/summary" },
  { path: "/performance/cycles" },
  { path: "/performance/reviews" },
  { path: "/performance/okrs" },
  { path: "/performance/360-reviews" },
  { path: "/performance/calibration" },
  { path: "/career/idps" },
  { path: "/career/succession" },
  { path: "/engagement/surveys" },
  { path: "/recruitment/openings" },

  { path: "/reports/competency" },
  { path: "/reports/employee-lifecycle" },
  { path: "/reports/custom" },
  { path: "/settings/user-role" },
  { path: "/settings/permissions" },
];

const RootRedirect = () => {
  const authStatus = useAuthSession();
  const dashboardPath = getRoleBasedDashboardPathFromStorage();

  if (authStatus === "checking") {
    return null;
  }

  return authStatus === "authenticated" ? (
    <Navigate to={dashboardPath} replace />
  ) : (
    <Navigate to="/login" replace />
  );
};

const GuestRoute = () => {
  const authStatus = useAuthSession();
  const dashboardPath = getRoleBasedDashboardPathFromStorage();

  if (authStatus === "checking") {
    return null;
  }

  return authStatus === "authenticated" ? <Navigate to={dashboardPath} replace /> : <Outlet />;
};

export const router = createBrowserRouter([
  {
    path: "/",
    element: <RootRedirect />,
  },
  {
    element: <GuestRoute />,
    children: [
      {
        path: "/login",
        element: <LoginPage />,
      },
      {
        path: "/auth/google/callback",
        element: <GoogleCallbackPage />,
      },
      {
        path: "/register",
        element: <RegisterPage />,
      },
      {
        path: "/forgot-password",
        element: <ForgotPasswordPage />,
      },
      {
        path: "/reset-password",
        element: <ResetPasswordPage />,
      },
    ],
  },
  {
    element: <ProtectedRoute />,
    children: [
      {
        path: "/approval-flows",
        element: <DashboardLayout />,
        children: [{ index: true, element: <ApprovalFlowPage /> }],
      },
      {
        path: "/dashboard",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <OverviewPage />,
          },
        ],
      },
      {
        path: "/employee-dashboard",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <EmployeeDashboardPage />,
          },
        ],
      },
      {
        path: "/attendance",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <AttendanceOverviewPage />,
          },
        ],
      },
      {
        path: "/attendance/check-in",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <AttendanceCheckInPage />,
          },
        ],
      },
      {
        path: "/attendance/check-out",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <AttendanceCheckOutPage />,
          },
        ],
      },
      {
        path: "/attendance/history",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <AttendanceHistoryPage />,
          },
        ],
      },
      {
        path: "/attendance/today",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <AttendanceTodayPage />,
          },
        ],
      },
      {
        path: "/kpis",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <KpiListPage />,
          },
          {
            path: "create",
            element: <KpiFormPage />,
          },
          {
            path: "edit/:id",
            element: <KpiFormPage />,
          },
        ],
      },
      {
        path: "/my/kpi",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <MyKpiPage />,
          },
        ],
      },
      {
        path: "/my/reimbursements",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <MyReimbursementsPage />,
          },
        ],
      },
      {
        path: "/my/assets",
        element: <DashboardLayout />,
        children: [{ index: true, element: <MyAssetsPage /> }],
      },
      {
        path: "/my/assignment-letters",
        element: <DashboardLayout />,
        children: [{ index: true, element: <MyAssignmentLettersPage /> }],
      },
      {
        path: "/my/tasks",
        element: <DashboardLayout />,
        children: [{ index: true, element: <MyTasksPage /> }],
      },
      {
        path: "/my/promotions",
        element: <DashboardLayout />,
        children: [{ index: true, element: <MyPromotionsPage /> }],
      },
      {
        path: "/my/overtime",
        element: <DashboardLayout />,
        children: [{ index: true, element: <OvertimePage /> }],
      },
      {
        path: "/my/documents",
        element: <DashboardLayout />,
        children: [{ index: true, element: <MyDocumentsPage /> }],
      },
      {
        path: "/my/payroll",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <MyPayrollPage />,
          },
        ],
      },
      {
        path: "/my/trainings",
        element: <DashboardLayout />,
        children: [{ index: true, element: <MyTrainingsPage /> }],
      },
      {
        path: "/my/competencies",
        element: <DashboardLayout />,
        children: [{ index: true, element: <MyCompetenciesPage /> }],
      },
      {
        path: "/leave/my-leave",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <MyLeavesPage />,
          },
        ],
      },
      {
        path: "/leave/balance",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <LeaveBalancePage />,
          },
        ],
      },
      {
        path: "/leave/requests",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <LeaveRequestsPage />,
          },
        ],
      },
      {
        path: "/leave/requests/create",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <CreateLeavePage />,
          },
        ],
      },
      {
        path: "/leave/request",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <CreateLeavePage />,
          },
        ],
      },
      {
        path: "/leave/requests/edit/:id",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <UpdateLeavePage />,
          },
        ],
      },
      {
        path: "/leave/requests/view/:id",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <UpdateLeavePage />,
          },
        ],
      },
      {
        path: "/leave/request/:id",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <UpdateLeavePage />,
          },
        ],
      },
      {
        path: "/leave/approval",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <LeaveApprovalPage />,
          },
        ],
      },
      {
        path: "/leave/calendar",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <LeaveCalendarPage />,
          },
        ],
      },
      {
        path: "/leave/policy",
        element: <DashboardLayout />,
        children: [
          { index: true, element: <LeavePolicyPage /> },
          { path: "create", element: <LeavePolicyFormPage /> },
          { path: "edit/:id", element: <LeavePolicyFormPage /> },
          { path: "manage", element: <SectionPage /> },
        ],
      },
      {
        path: "/leave/type",
        element: <DashboardLayout />,
        children: [
          { index: true, element: <LeaveTypePage /> },
          { path: "create", element: <LeaveTypeFormPage /> },
          { path: "edit/:id", element: <LeaveTypeFormPage /> },
          { path: "manage", element: <SectionPage /> },
        ],
      },
      {
        path: "/settings/master-data/leave-type",
        element: <Navigate to="/leave/type" replace />,
      },
      {
        path: "/profiles",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <ProfilesPage />,
          },
        ],
      },
      {
        path: "/profiles/add",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <ProfilesPage />,
          },
        ],
      },
      {
        path: "/profiles/view/:id",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <ProfilesPage />,
          },
        ],
      },
      {
        path: "/profiles/update/:id",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <ProfilesPage />,
          },
        ],
      },
      {
        path: "/my/reimbursements",
        element: <DashboardLayout />,
        children: [{ index: true, element: <MyReimbursementsPage /> }],
      },
      {
        path: "/reimbursements",
        element: <DashboardLayout />,
        children: [{ index: true, element: <AdminReimbursementsPage /> }],
      },
      {
        path: "/expense/submit",
        element: <DashboardLayout />,
        children: [{ index: true, element: <MyReimbursementsPage /> }],
      },
      {
        path: "/expense/list",
        element: <DashboardLayout />,
        children: [{ index: true, element: <AdminReimbursementsPage /> }],
      },
      {
        path: "/expense/approval",
        element: <DashboardLayout />,
        children: [{ index: true, element: <AdminReimbursementsPage /> }],
      },
      {
        path: "/locations",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: (
              <MenuRouteGuard menuKey="alat-admin.master.lokasi">
                <LocationsPage />
              </MenuRouteGuard>
            ),
          },
        ],
      },
      {
        path: "/locations/create",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: (
              <MenuRouteGuard menuKey="alat-admin.master.lokasi">
                <CreateLocationPage />
              </MenuRouteGuard>
            ),
          },
        ],
      },
      {
        path: "/locations/edit/:id",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: (
              <MenuRouteGuard menuKey="alat-admin.master.lokasi">
                <EditLocationPage />
              </MenuRouteGuard>
            ),
          },
        ],
      },
      {
        path: "/work-schedules",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: (
              <MenuRouteGuard menuKey="alat-admin.master.jadwal-kerja">
                <WorkSchedulesPage />
              </MenuRouteGuard>
            ),
          },
        ],
      },
      {
        path: "/work-schedules/add",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: (
              <MenuRouteGuard menuKey="alat-admin.master.jadwal-kerja">
                <WorkScheduleCreatePage />
              </MenuRouteGuard>
            ),
          },
        ],
      },
      {
        path: "/work-schedules/edit/:id",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: (
              <MenuRouteGuard menuKey="alat-admin.master.jadwal-kerja">
                <WorkScheduleEditPage />
              </MenuRouteGuard>
            ),
          },
        ],
      },
      {
        path: "/admin",
        element: <DashboardLayout />,
        children: [
          {
            path: "users",
            element: (
              <MenuRouteGuard menuKey="alat-admin.manajemen-akses.pengguna">
                <AdminUsersPage />
              </MenuRouteGuard>
            ),
          },
          {
            path: "users/assign-roles",
            element: (
              <MenuRouteGuard menuKey="alat-admin.manajemen-akses.pengguna">
                <AdminUserAssignRolesPage />
              </MenuRouteGuard>
            ),
          },
          {
            path: "roles",
            element: (
              <MenuRouteGuard menuKey="alat-admin.manajemen-akses.peran">
                <AdminRolesPage />
              </MenuRouteGuard>
            ),
          },
          {
            path: "roles/create",
            element: (
              <MenuRouteGuard menuKey="alat-admin.manajemen-akses.peran">
                <AdminRoleFormPage />
              </MenuRouteGuard>
            ),
          },
          {
            path: "roles/edit/:id",
            element: (
              <MenuRouteGuard menuKey="alat-admin.manajemen-akses.peran">
                <AdminRoleFormPage />
              </MenuRouteGuard>
            ),
          },
          {
            path: "roles/assign-permissions",
            element: (
              <MenuRouteGuard menuKey="alat-admin.manajemen-akses.peran">
                <AdminRoleAssignPermissionsPage />
              </MenuRouteGuard>
            ),
          },
          {
            path: "permissions",
            element: (
              <MenuRouteGuard menuKey="alat-admin.manajemen-akses.izin">
                <AdminPermissionsPage />
              </MenuRouteGuard>
            ),
          },
          {
            path: "menu-permissions",
            element: (
              <MenuRouteGuard menuKey="alat-admin.manajemen-akses.menu">
                <MenuPermissionsPage />
              </MenuRouteGuard>
            ),
          },
          {
            path: "notifications",
            element: (
              <MenuRouteGuard menuKey="alat-admin.notifikasi.admin">
                <AdminNotificationsPage />
              </MenuRouteGuard>
            ),
          },
          {
            path: "notifications/email-send",
            element: (
              <MenuRouteGuard menuKey="alat-admin.notifikasi.kirim-email">
                <AdminEmailSendPage />
              </MenuRouteGuard>
            ),
          },
          {
            path: "notifications/email-logs",
            element: (
              <MenuRouteGuard menuKey="alat-admin.notifikasi.log-email">
                <AdminEmailNotificationsPage />
              </MenuRouteGuard>
            ),
          },
          {
            path: "audit-logs",
            element: (
              <MenuRouteGuard menuKey="alat-admin.sistem.log-audit">
                <AdminAuditLogsPage />
              </MenuRouteGuard>
            ),
          },
          {
            path: "import",
            element: (
              <MenuRouteGuard menuKey="master-data.pusat-impor">
                <AdminImportPage />
              </MenuRouteGuard>
            ),
          },
          {
            path: "biometric-devices",
            element: (
              <MenuRouteGuard menuKey="alat-admin.sistem.biometrik">
                <AdminBiometricDevicesPage />
              </MenuRouteGuard>
            ),
          },
        ],
      },
      {
        element: <DashboardLayout />,
        children: [
          {
            path: "/admin/biometric-devices",
            element: (
              <MenuRouteGuard menuKey="alat-admin.sistem.biometrik">
                <AdminBiometricDevicesPage />
              </MenuRouteGuard>
            ),
          },

          { path: "/recruitment/openings", element: <JobOpeningsPage /> },
          { path: "/recruitment/openings/create", element: <JobOpeningFormPage /> },
          { path: "/recruitment/openings/edit/:id", element: <JobOpeningFormPage /> },
          { path: "/recruitment/candidates", element: <CandidatePipelinePage /> },
          { path: "/recruitment/talent-pool", element: <TalentPoolPage /> },
          
          { path: "/performance/okrs", element: <MenuRouteGuard menuKey="kpi-kinerja"><OkrManagementPage /></MenuRouteGuard> },
          { path: "/performance/okrs/create", element: <MenuRouteGuard menuKey="kpi-kinerja"><OkrFormPage /></MenuRouteGuard> },
          { path: "/performance/okrs/edit/:id", element: <MenuRouteGuard menuKey="kpi-kinerja"><OkrFormPage /></MenuRouteGuard> },
          
          { path: "/engagement/surveys", element: <EngagementSurveysPage /> },
          { path: "/engagement/surveys/create", element: <SurveyFormPage /> },
          { path: "/engagement/surveys/edit/:id", element: <SurveyFormPage /> },
          { path: "/engagement/analytics", element: <EngagementAnalyticsPage /> },
          { path: "/engagement/analytics/:id", element: <EngagementAnalyticsPage /> },

          { path: "/legal/severance", element: <SeveranceCalculatorPage /> },
          { path: "/legal/letters", element: <EmploymentLettersPage /> },
          { path: "/legal/tax", element: <ProgressiveTaxPage /> },
          { path: "/admin/assignment-letters", element: <AssignmentLettersPage /> },
          
          { path: "/organization/chart", element: <OrgChartPage /> },
          { path: "/hr-requests", element: <HrRequestsPage /> },
          { path: "/hr-requests/respond/:id", element: <HrRequestFormPage /> },
          { path: "/hr-requests/sla", element: <SlaPage /> },
          
          { path: "/compliance/overview", element: <ComplianceDashboardPage /> },
          { path: "/compliance/settings", element: <ComplianceSettingsPage /> },
          
          { path: "/workforce/holidays", element: <HolidayCalendarPage /> },
          { path: "/workforce/holidays/create", element: <HolidayFormPage /> },
          { path: "/workforce/holidays/edit/:id", element: <HolidayFormPage /> },

          { path: "/training", element: <Navigate to="/training/programs" replace /> },
          { path: "/training/programs", element: <MenuRouteGuard menuKey="pelatihan-kompetensi.pelatihan"><TrainingManagementPage /></MenuRouteGuard> },
          { path: "/training/programs/create", element: <MenuRouteGuard menuKey="pelatihan-kompetensi.pelatihan"><TrainingFormPage /></MenuRouteGuard> },
          { path: "/training/programs/edit/:id", element: <MenuRouteGuard menuKey="pelatihan-kompetensi.pelatihan"><TrainingFormPage /></MenuRouteGuard> },
          { path: "/training/enrollments", element: <Navigate to="/training/programs" replace /> },

          { path: "/competencies", element: <CompetencyMatrixPage /> },
          { path: "/career/succession", element: <SuccessionMatrixPage /> },
          { path: "/career/idps", element: <IdpPage /> },

          { path: "/performance/calibration", element: <CalibrationPage /> },
          { path: "/performance/calibration/create", element: <CalibrationFormPage /> },
          { path: "/performance/calibration/edit/:id", element: <CalibrationFormPage /> },
          { path: "/performance/reviews", element: <Review360Page /> },
          
          { path: "/workforce/shift-swaps", element: <ShiftSwapsPage /> },
          { path: "/workforce/shift-swaps/create", element: <ShiftSwapFormPage /> },
          { path: "/workforce/shift-swaps/edit/:id", element: <ShiftSwapFormPage /> },
          { path: "/workforce/overtime-rules", element: <OvertimeRulesPage /> },

          { path: "/workforce/overtime-rules/create", element: <OvertimeRuleFormPage /> },
          { path: "/workforce/overtime-rules/edit/:id", element: <OvertimeRuleFormPage /> },

          { path: "/biometric/devices", element: <MenuRouteGuard menuKey="alat-admin.sistem.biometrik"><BiometricDevicesPage /></MenuRouteGuard> },
          { path: "/enterprise/compensation", element: <CompensationPage /> },
          { path: "/inventory/assets", element: <MenuRouteGuard menuKey="assets"><AssetManagementPage /></MenuRouteGuard> },
          { path: "/assets", element: <MenuRouteGuard menuKey="assets"><AssetManagementPage /></MenuRouteGuard> },

          { path: "/inventory/assets/create", element: <MenuRouteGuard menuKey="assets"><AssetFormPage /></MenuRouteGuard> },
          { path: "/inventory/assets/edit/:id", element: <MenuRouteGuard menuKey="assets"><AssetFormPage /></MenuRouteGuard> },
          { path: "/tasks", element: <TaskManagementPage /> },
          { path: "/promotions", element: <PromotionPage /> },
          { path: "/admin/assignment-letters", element: <AssignmentLettersPage /> },
          { path: "/compensation/benefits", element: <BenefitManagementPage /> },
          { path: "/compensation/benefits/create", element: <BenefitFormPage /> },
          { path: "/compensation/benefits/edit/:id", element: <BenefitFormPage /> },

          { path: "/analytics/people-detailed", element: <DetailedPeopleAnalyticsPage /> },
          { path: "/enterprise/notification-rules", element: <NotificationRulesPage /> },

          {
            path: "/settings",
            children: [
              { path: "company", element: <CompanySettingsPage /> },
              { path: "notification", element: <NotificationSettingsPage /> },
              { path: "notifications", element: <NotificationSettingsPage /> },
            ]
          },
          { path: "/organization/master-data", element: <MenuRouteGuard menuKey="master-data"><MasterDataPage /></MenuRouteGuard> },
        ],
      },
      {
        path: "/notifications",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <NotificationsPage />,
          },
        ],
      },
      {
        path: "/payroll",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <PayrollDashboard />,
          },
        ],
      },
      {
        path: "/payroll/run",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <PayrollManagementPage />,
          },
        ],
      },
      {
        path: "/payroll/list",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <PayrollListPage />,
          },
        ],
      },
      {
        path: "/payroll/process",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <PayrollProcessPage />,
          },
        ],
      },
      {
        path: "/payroll/reports",
        element: <DashboardLayout />,
        children: [{ index: true, element: <PayrollReportsPage /> }],
      },
      {
        path: "/payroll/component",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <PayrollDetailsPage />,
          },
        ],
      },
      { path: "/payroll/crud", element: <DashboardLayout />, children: [{ index: true, element: <Navigate to="/payroll/list" replace /> }] },
      { path: "/payroll/generate", element: <DashboardLayout />, children: [{ index: true, element: <Navigate to="/payroll/process" replace /> }] },
      { path: "/payroll/approve", element: <DashboardLayout />, children: [{ index: true, element: <Navigate to="/payroll/process" replace /> }] },
      { path: "/payroll/payment", element: <DashboardLayout />, children: [{ index: true, element: <Navigate to="/payroll/process" replace /> }] },
      { path: "/payroll/tax", element: <DashboardLayout />, children: [{ index: true, element: <Navigate to="/payroll/reports" replace /> }] },
      {
        path: "/reports",
        element: <DashboardLayout />,
        children: [{ index: true, element: <Navigate to="/reports/dashboard-summary" replace /> }],
      },
      {
        path: "/reports/dashboard-summary",
        element: <DashboardLayout />,
        children: [{ index: true, element: <ReportsDashboardPage /> }],
      },
      {
        path: "/reports/attendance",
        element: <DashboardLayout />,
        children: [{ index: true, element: <Navigate to="/reports/dashboard-summary" replace /> }],
      },
      {
        path: "/reports/leave",
        element: <DashboardLayout />,
        children: [{ index: true, element: <Navigate to="/reports/dashboard-summary" replace /> }],
      },
      {
        path: "/reports/payroll",
        element: <DashboardLayout />,
        children: [{ index: true, element: <Navigate to="/reports/dashboard-summary" replace /> }],
      },
      {
        path: "/reports/assets",
        element: <DashboardLayout />,
        children: [{ index: true, element: <Navigate to="/reports/dashboard-summary" replace /> }],
      },
      {
        path: "/reports/employee",
        element: <DashboardLayout />,
        children: [{ index: true, element: <Navigate to="/reports/dashboard-summary" replace /> }],
      },
      {
        path: "/attendance/overtime",
        element: <DashboardLayout />,
        children: [{ index: true, element: <OvertimePage /> }],
      },
      {
        path: "/attendance/reports",
        element: <DashboardLayout />,
        children: [{ index: true, element: <AttendanceReportsPage /> }],
      },
      ...sectionRoutes.map((route) => ({
        path: route.path,
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <SectionPage />,
          },
        ],
      })),
      {
        path: "*",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: <SectionPage />,
          },
        ],
      },
    ],
  },
  {
    element: <ProtectedRoute />,
    children: [
      {
        path: "/employees",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: (
              <MenuRouteGuard menuKey="employees">
                <EmployeesPage />
              </MenuRouteGuard>
            ),
          },
        ],
      },
      {
        path: "/employees/add",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: (
              <MenuRouteGuard menuKey="employees">
                <EmployeeCreatePage />
              </MenuRouteGuard>
            ),
          },
        ],
      },
      {
        path: "/employees/update/:id",
        element: <DashboardLayout />,
        children: [
          {
            index: true,
            element: (
              <MenuRouteGuard menuKey="employees">
                <EmployeeEditPage />
              </MenuRouteGuard>
            ),
          },
        ],
      },
    ],
  },
  {
    path: "*",
    element: <NotFoundPage />,
  },
]);

