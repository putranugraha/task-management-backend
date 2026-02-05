"use client";

import { useEffect, useMemo, useState } from "react";
import dynamic from "next/dynamic";
import { apiRequest } from "@/lib/api";
import type { Project } from "@/types/project";
import type { KpiSnapshot } from "@/types/kpi-snapshot";
import type { ReportingPeriod } from "@/types/reporting-period";
import { Skeleton } from "@/components/ui/skeleton";
import { useToast } from "@/components/ui/toast";

type TaskStats = {
  total: number;
  completed: number;
  in_progress: number;
};

type MilestoneStats = {
  total: number;
  completed: number;
  overdue: number;
};

const EvmWidget = dynamic(
  () => import("@/components/evm/EvmWidget"),
  {
    ssr: false,
    loading: () => (
      <div className="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-500 shadow-sm">
        Memuat ringkasan EVM…
      </div>
    ),
  }
);

type ProjectSummary = {
  id: number;
  name: string;
};

type KpiSnapshotWithPeriod = KpiSnapshot & {
  period_label: string;
};

function toISODate(d: Date): string {
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export default function ReportsPage() {
  const { showToast } = useToast();

  const [projects, setProjects] = useState<ProjectSummary[]>([]);
  const [projectsLoading, setProjectsLoading] = useState(false);
  const [projectsError, setProjectsError] = useState<string | null>(null);

  const [selectedProjectId, setSelectedProjectId] = useState<number | null>(
    null
  );

  const today = useMemo(() => new Date(), []);
  const startOfMonth = useMemo(
    () => new Date(today.getFullYear(), today.getMonth(), 1),
    [today]
  );

  const [dateFrom, setDateFrom] = useState<string>(toISODate(startOfMonth));
  const [dateTo, setDateTo] = useState<string>(toISODate(today));

  const [taskStats, setTaskStats] = useState<TaskStats | null>(null);
  const [taskStatsLoading, setTaskStatsLoading] = useState(false);
  const [taskStatsError, setTaskStatsError] = useState<string | null>(null);

  const [milestoneStats, setMilestoneStats] = useState<MilestoneStats | null>(
    null
  );
  const [milestoneStatsLoading, setMilestoneStatsLoading] = useState(false);
  const [milestoneStatsError, setMilestoneStatsError] = useState<
    string | null
  >(null);

  const [kpiSnapshots, setKpiSnapshots] = useState<KpiSnapshotWithPeriod[]>([]);
  const [kpiLoading, setKpiLoading] = useState(false);
  const [kpiError, setKpiError] = useState<string | null>(null);
  const [avgCycleTime, setAvgCycleTime] = useState<number | null>(null);

  // Load projects for dropdown
  useEffect(() => {
    let cancelled = false;
    const run = async () => {
      setProjectsLoading(true);
      setProjectsError(null);
      try {
        const res = await apiRequest<
          Project[] | { data: Project[]; meta?: unknown }
        >("GET", `/api/projects?per_page=50`);
        const arr = Array.isArray(res) ? res : (res as any)?.data ?? [];
        const mapped: ProjectSummary[] = arr.map((p: any) => ({
          id: Number(p.id),
          name: String(p.name ?? `Project #${p.id}`),
        }));
        if (!cancelled) {
          setProjects(mapped);
          if (mapped.length > 0 && selectedProjectId == null) {
            setSelectedProjectId(mapped[0].id);
          }
        }
      } catch (e: any) {
        const msg = e?.message ?? "Gagal memuat daftar project";
        setProjectsError(msg);
        showToast({
          variant: "error",
          title: "Gagal memuat projects",
          description: msg,
        });
      } finally {
        if (!cancelled) {
          setProjectsLoading(false);
        }
      }
    };
    run();
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Load stats when project changes
  useEffect(() => {
    if (!selectedProjectId) {
      setTaskStats(null);
      setMilestoneStats(null);
      return;
    }
    let cancelled = false;
    const run = async () => {
      setTaskStatsLoading(true);
      setMilestoneStatsLoading(true);
      setTaskStatsError(null);
      setMilestoneStatsError(null);
      try {
        const params = new URLSearchParams();
        params.set("project_id", String(selectedProjectId));
        const [tRes, mRes] = await Promise.all([
          apiRequest<TaskStats>("GET", `/api/tasks/stats?${params.toString()}`)
            .catch(() => null),
          apiRequest<MilestoneStats>(
            "GET",
            `/api/milestones/stats?${params.toString()}`
          ).catch(() => null),
        ]);

        if (cancelled) return;

        if (tRes) {
          setTaskStats({
            total: tRes.total ?? 0,
            completed: tRes.completed ?? 0,
            in_progress: tRes.in_progress ?? 0,
          });
        } else {
          setTaskStats(null);
        }

        if (mRes) {
          setMilestoneStats({
            total: mRes.total ?? 0,
            completed: mRes.completed ?? 0,
            overdue: mRes.overdue ?? 0,
          });
        } else {
          setMilestoneStats(null);
        }
      } catch (e: any) {
        if (cancelled) return;
        const msg = e?.message ?? "Gagal memuat ringkasan";
        setTaskStatsError(msg);
        setMilestoneStatsError(msg);
      } finally {
        if (!cancelled) {
          setTaskStatsLoading(false);
          setMilestoneStatsLoading(false);
        }
      }
    };
    run();
    return () => {
      cancelled = true;
    };
  }, [selectedProjectId]);

  // Load KPI snapshots per project
  useEffect(() => {
    if (!selectedProjectId) {
      setKpiSnapshots([]);
      setAvgCycleTime(null);
      return;
    }
    let cancelled = false;
    const run = async () => {
      setKpiLoading(true);
      setKpiError(null);
      try {
        const [periodsRes, snapsRes, avgRes] = await Promise.all([
          apiRequest<ReportingPeriod[] | { data: ReportingPeriod[] }>(
            "GET",
            `/api/projects/${encodeURIComponent(
              String(selectedProjectId)
            )}/reporting-periods`
          ).catch(() => []),
          apiRequest<
            KpiSnapshot[] | { data: KpiSnapshot[] } | KpiSnapshot | { data: KpiSnapshot }
          >(
            "GET",
            `/api/projects/${encodeURIComponent(
              String(selectedProjectId)
            )}/kpi-snapshots`
          ).catch(() => [] as any),
          apiRequest<{ average_cycle_time_days?: number } | any>(
            "GET",
            `/api/projects/${encodeURIComponent(
              String(selectedProjectId)
            )}/kpi-snapshots/average-cycle-time`
          ).catch(() => null),
        ]);

        if (cancelled) return;

        const periods: ReportingPeriod[] = Array.isArray(periodsRes)
          ? periodsRes
          : (periodsRes as any)?.data ?? [];

        let rawSnaps: KpiSnapshot[] = [];
        const snapsPayload: any = snapsRes;
        if (Array.isArray(snapsPayload)) {
          rawSnaps = snapsPayload as KpiSnapshot[];
        } else if (
          snapsPayload &&
          typeof snapsPayload === "object" &&
          "data" in snapsPayload
        ) {
          const inner = (snapsPayload as any).data;
          if (Array.isArray(inner)) {
            rawSnaps = inner;
          } else if (inner) {
            rawSnaps = [inner as KpiSnapshot];
          }
        } else if (snapsPayload) {
          rawSnaps = [snapsPayload as KpiSnapshot];
        }

        const periodMap = new Map<number, ReportingPeriod>();
        periods.forEach((p) => {
          periodMap.set(Number(p.id), p);
        });

        const withLabel: KpiSnapshotWithPeriod[] = rawSnaps.map((s) => {
          const rp =
            s.reporting_period ??
            periodMap.get(Number((s as any).period_id)) ??
            null;
          const label =
            (rp && (rp as any).period_date) ||
            (s as any).period_date ||
            (rp && (rp as any).id
              ? `Periode #${(rp as any).id}`
              : `Periode #${s.period_id}`);
          return {
            ...(s as any),
            period_label: String(label ?? `Periode #${s.period_id}`),
          } as KpiSnapshotWithPeriod;
        });

        withLabel.sort((a, b) => {
          const da = (a.reporting_period as any)?.period_date ?? a.created_at;
          const db = (b.reporting_period as any)?.period_date ?? b.created_at;
          const ta = da ? Date.parse(da) : 0;
          const tb = db ? Date.parse(db) : 0;
          return ta - tb;
        });

        let avg: number | null = null;
        if (avgRes && typeof avgRes === "object") {
          if ("average_cycle_time_days" in avgRes) {
            const v = (avgRes as any).average_cycle_time_days;
            const n = typeof v === "number" ? v : Number(v ?? NaN);
            avg = Number.isFinite(n) ? n : null;
          }
        }

        setKpiSnapshots(withLabel);
        setAvgCycleTime(avg);
      } catch (e: any) {
        if (cancelled) return;
        const msg = e?.message ?? "Gagal memuat KPI snapshots";
        setKpiError(msg);
      } finally {
        if (!cancelled) {
          setKpiLoading(false);
        }
      }
    };
    run();
    return () => {
      cancelled = true;
    };
  }, [selectedProjectId]);

  const selectedProject =
    selectedProjectId && projects.length
      ? projects.find((p) => p.id === selectedProjectId) ?? null
      : null;

  const latestKpi = useMemo(() => {
    if (!kpiSnapshots.length) return null;
    return kpiSnapshots[kpiSnapshots.length - 1];
  }, [kpiSnapshots]);

  const handlePrint = () => {
    if (typeof window === "undefined") return;
    try {
      window.print();
    } catch {
      // ignore
    }
  };

  const handleExportKpiCsv = () => {
    if (!kpiSnapshots.length || !selectedProject) return;
    const headers = [
      "Project ID",
      "Project Name",
      "Period",
      "Tasks Total",
      "Tasks Done",
      "Overdue Count",
      "Avg Cycle Time (days)",
    ];
    const rows = kpiSnapshots.map((s) => [
      String(selectedProject.id),
      selectedProject.name,
      s.period_label,
      String(s.tasks_total ?? 0),
      String(s.tasks_done ?? 0),
      String(s.overdue_count ?? 0),
      String(s.avg_cycle_time_days ?? 0),
    ]);

    const csv = [headers, ...rows]
      .map((line) =>
        line
          .map((cell) => {
            const value = cell ?? "";
            if (/[",\n]/.test(value)) {
              return `"${value.replace(/"/g, '""')}"`;
            }
            return value;
          })
          .join(",")
      )
      .join("\r\n");

    const blob =
      typeof Blob !== "undefined"
        ? new Blob([csv], { type: "text/csv;charset=utf-8;" })
        : null;
    if (!blob || typeof window === "undefined") return;

    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    const safeName = selectedProject.name.replace(/[^a-z0-9_-]+/gi, "_");
    a.download = `kpi_report_${safeName || selectedProject.id}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between print:block">
        <div>
          <h1 className="text-xl font-semibold tracking-tight text-slate-900">
            Reports
          </h1>
          <p className="mt-1 text-sm text-slate-500">
            Ringkasan performa proyek, KPI snapshot, dan EVM. Gunakan filter
            project di bawah ini lalu cetak atau export laporan.
          </p>
        </div>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
          <div className="space-y-1">
            <label className="block text-xs font-medium text-slate-600">
              Project
            </label>
            <select
              className="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-800 shadow-sm focus:outline-none focus:ring-2 focus:ring-[#00674F]"
              value={selectedProjectId ?? ""}
              onChange={(e) => {
                const v = e.target.value;
                setSelectedProjectId(v ? Number(v) : null);
              }}
              disabled={projectsLoading}
            >
              <option value="">
                {projectsLoading ? "Memuat projects…" : "Pilih project"}
              </option>
              {projects.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
            {projectsError && (
              <p className="text-xs text-red-600">{projectsError}</p>
            )}
          </div>
          <div className="flex gap-3">
            <div className="space-y-1">
              <label className="block text-xs font-medium text-slate-600">
                Dari
              </label>
              <input
                type="date"
                className="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-800 shadow-sm focus:outline-none focus:ring-2 focus:ring-[#00674F]"
                value={dateFrom}
                onChange={(e) => setDateFrom(e.target.value)}
              />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium text-slate-600">
                Sampai
              </label>
              <input
                type="date"
                className="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-800 shadow-sm focus:outline-none focus:ring-2 focus:ring-[#00674F]"
                value={dateTo}
                onChange={(e) => setDateTo(e.target.value)}
              />
            </div>
          </div>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={handlePrint}
              className="inline-flex h-9 items-center justify-center rounded-full border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-[#00674F] hover:text-[#00674F] print:hidden"
            >
              Cetak laporan
            </button>
            <button
              type="button"
              onClick={handleExportKpiCsv}
              disabled={!kpiSnapshots.length || !selectedProject}
              className="inline-flex h-9 items-center justify-center rounded-full border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-[#00674F] hover:text-[#00674F] disabled:cursor-not-allowed disabled:opacity-50 print:hidden"
            >
              Export KPI (CSV)
            </button>
          </div>
        </div>
      </div]
