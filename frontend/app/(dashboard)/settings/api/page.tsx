import { redirect } from "next/navigation";

export default function APIRedirect() {
  redirect("/configuration/api");
}
