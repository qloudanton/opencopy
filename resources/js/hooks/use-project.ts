import { type Project, type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect } from 'react';

const LAST_PROJECT_KEY = 'opencopy_last_project_id';

export function useProject() {
    const { projects, currentProject } = usePage<SharedData>().props;

    // Store last project in localStorage when it changes
    useEffect(() => {
        if (currentProject) {
            localStorage.setItem(LAST_PROJECT_KEY, String(currentProject.id));
        }
    }, [currentProject]);

    const getLastProjectId = useCallback((): number | null => {
        if (typeof window === 'undefined') return null;
        const stored = localStorage.getItem(LAST_PROJECT_KEY);
        return stored ? parseInt(stored, 10) : null;
    }, []);

    const getLastProject = useCallback((): Project | null => {
        const lastId = getLastProjectId();
        if (!lastId) return null;
        return projects.find((p) => p.id === lastId) ?? null;
    }, [projects, getLastProjectId]);

    const switchProject = useCallback((projectId: number) => {
        router.visit(`/projects/${projectId}`);
    }, []);

    const clearLastProject = useCallback(() => {
        localStorage.removeItem(LAST_PROJECT_KEY);
    }, []);

    return {
        projects,
        currentProject,
        getLastProjectId,
        getLastProject,
        switchProject,
        clearLastProject,
        hasProjects: projects.length > 0,
    };
}
