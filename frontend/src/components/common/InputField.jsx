// File: src/components/common/InputField.jsx
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

export default function InputField({ id, label, error, ...props }) {
  return (
    <div className="flex flex-col gap-1">
      {label && <Label htmlFor={id}>{label}</Label>}
      <Input id={id} aria-describedby={error ? `${id}-error` : undefined} {...props} />
      {error && (
        <p id={`${id}-error`} className="text-sm text-red-500">
          {error}
        </p>
      )}
    </div>
  );
}
