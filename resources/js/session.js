import { reactive } from "vue";
import { http, unwrap } from "./http";
export const session = reactive({ user: null });
export async function loadSession() {
    session.user = unwrap(await http.get("/profile"));
    return session.user;
}
export function managesGroup(id) {
    return session.user?.groups?.some(
        (group) =>
            group.id === id &&
            ["owner", "moderator"].includes(group.role_in_group),
    );
}
