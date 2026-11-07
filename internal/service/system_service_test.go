package service

import "testing"

func TestIsNewerVersion(t *testing.T) {
	tests := []struct {
		name    string
		latest  string
		current string
		want    bool
	}{
		{name: "newer patch", latest: "2.361", current: "2.360", want: true},
		{name: "older release", latest: "2.359", current: "2.360", want: false},
		{name: "same version", latest: "2.360", current: "2.360", want: false},
		{name: "numeric segments", latest: "2.100", current: "2.99", want: true},
		{name: "newer major", latest: "3.0", current: "2.360", want: true},
		{name: "release tag prefix", latest: "v2.361", current: "2.360", want: true},
		{name: "equivalent zero padding", latest: "2.360.0", current: "2.360", want: false},
		{name: "missing latest", latest: "", current: "2.360", want: false},
		{name: "invalid latest", latest: "development", current: "2.360", want: false},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := isNewerVersion(tt.latest, tt.current); got != tt.want {
				t.Fatalf("isNewerVersion(%q, %q) = %v, want %v", tt.latest, tt.current, got, tt.want)
			}
		})
	}
}
