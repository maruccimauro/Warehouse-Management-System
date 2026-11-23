import { useEffect, useState } from 'react';

// Cuenta regresiva en segundos. La deriva de setTimeout es irrelevante para esperas de
// pocos minutos como el Retry-After del login.
export function useCountdown(): [remaining: number, start: (seconds: number) => void] {
  const [remaining, setRemaining] = useState(0);

  useEffect(() => {
    if (remaining <= 0) {
      return undefined;
    }

    const timer = setTimeout(() => {
      setRemaining((current) => current - 1);
    }, 1000);

    return () => {
      clearTimeout(timer);
    };
  }, [remaining]);

  const start = (seconds: number) => {
    setRemaining(Math.max(0, Math.ceil(seconds)));
  };

  return [remaining, start];
}
