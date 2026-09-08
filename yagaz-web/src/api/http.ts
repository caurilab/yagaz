import type { AxiosResponse } from 'axios'

/** Déballe l'enveloppe `{ data }` (convention Laravel Resources, docs/09). */
export async function unwrap<T>(request: Promise<AxiosResponse<{ data: T }>>): Promise<T> {
  const response = await request
  return response.data.data
}
