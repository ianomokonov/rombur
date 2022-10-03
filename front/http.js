export const TOKEN_KEY = "romBurToken";
export const REFRESH_TOKEN_KEY = "romBurRefreshToken";
const BASIS_URL = "http://stand2.progoff.ru/back";

export const get = async (url) => {
  const response = await fetch(`${BASIS_URL}/${url}`);
  return await response.json();
};

export const post = async (url, body) => {
  const response = await fetch(`${BASIS_URL}/${url}`, {
    method: "POST",
    headers: getHeaders(),
    body: JSON.stringify(body),
  });
  if (response.ok) {
    return await response.json();
  }
  throw new Error(await response.json());
};

export const put = async (url, body) => {
  const response = await fetch(`${BASIS_URL}/${url}`, {
    method: "PUT",
    headers: getHeaders(),
    body: JSON.stringify(body),
  });
  if (response.ok) {
    return await response.json();
  }
  throw new Error(await response.json());
};

function getHeaders() {
  const headers = {
    "Content-Type": "application/json",
  };

  const token = sessionStorage.getItem(TOKEN_KEY);

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  return headers;
}
