// File: src/components/common/Button.jsx
import { Loader2 } from "lucide-react";
import { Button as ShadButton } from "@/components/ui/button";

export default function Button({ loading = false, children, disabled, ...props }) {
  return (
    <ShadButton disabled={loading || disabled} {...props}>
      {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
      {children}
    </ShadButton>
  );
}
