import axios from "axios";

export const http = axios.create({
    baseURL: "/web",
    timeout: 20000,
    headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
    },
});
export const unwrap = (response) => response.data?.data ?? response.data;
export const errorText = (error) => {
    if (error.response?.status === 419)
        return "Sua sessão expirou. Atualize a página e tente novamente.";
    if (error.response?.status === 403)
        return (
            error.response.data?.message ||
            "Você não tem permissão para esta ação."
        );
    return (
        Object.values(error.response?.data?.errors ?? {})
            .flat()
            .join(" ") ||
        error.response?.data?.message ||
        "Não foi possível conectar. Verifique sua internet e tente novamente."
    );
};
http.interceptors.request.use((config) => {
    config.headers["X-CSRF-TOKEN"] = document.querySelector(
        'meta[name="csrf-token"]',
    )?.content;
    return config;
});
http.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401 && location.pathname !== "/login")
            location.assign("/login");
        return Promise.reject(error);
    },
);
export async function refreshCsrf() {
    const { data } = await http.get("/session/csrf", { baseURL: "/" });
    document.querySelector('meta[name="csrf-token"]').content = data.token;
}
export function cleanParams(values) {
    return Object.fromEntries(
        Object.entries(values).filter(
            ([, value]) =>
                value !== "" && value !== null && value !== undefined,
        ),
    );
}
export const money = (value) =>
    value == null
        ? "Sob consulta"
        : new Intl.NumberFormat("pt-BR", {
              style: "currency",
              currency: "BRL",
              maximumFractionDigits: 0,
          }).format(value);
export const dateTime = (value) =>
    value
        ? new Date(value).toLocaleString("pt-BR", {
              dateStyle: "short",
              timeStyle: "short",
          })
        : "";
export const propertyTypes = {
    apartment: "Apartamento",
    house: "Casa",
    land: "Terreno",
    commercial: "Comercial",
    development: "Empreendimento",
};
export const propertyStatuses = {
    active: "Disponível",
    draft: "Rascunho",
    reserved: "Reservado",
    sold: "Vendido",
};
export const dealStatuses = {
    initiated: "Aguardando aceite",
    proposal: "Em conversa",
    under_review: "Em análise",
    in_escrow: "Em contrato",
    closed: "Concluído",
    lost: "Perdido",
    cancelled: "Cancelado",
};
